#!/bin/sh
#
# Builds static Linux binaries of the bundled optimizers inside Alpine (musl), so they run on
# every distribution. Run on the target architecture, or with `docker --platform`:
#
#   docker run --rm -v "$PWD:/work" -w /work alpine:3.20 sh bin/build-linux.sh
#
# Output: build/linux-<arch>/{jpegoptim,pngquant,gifsicle,optipng,cwebp}. Copy those into
# bin/linux-<arch>/ and commit. See BUILD.md. The GitHub workflow "Build Linux binaries" runs this
# for x86_64 and aarch64 and uploads the results as artifacts.

set -eu

JPEGOPTIM=1.5.6
PNGQUANT=2.18.0
GIFSICLE=1.96
OPTIPNG=0.7.8
LIBWEBP=1.6.0

ARCH=$(uname -m)
OUT=$(pwd)/build/linux-$ARCH
WORK=$(mktemp -d)

apk add --no-cache build-base bash curl tar file \
    libjpeg-turbo-dev libjpeg-turbo-static libpng-dev libpng-static zlib-dev zlib-static

# fetch <url> <sha256>: download into $WORK, verify, print the path
fetch() {
    file="$WORK/$(basename "$1")"
    curl -sSfL -o "$file" "$1"
    echo "$2  $file" | sha256sum -c - >/dev/null
    echo "$file"
}

mkdir -p "$OUT"
cd "$WORK"

# jpegoptim, against libjpeg-turbo
tar -xzf "$(fetch "https://github.com/tjko/jpegoptim/archive/refs/tags/v$JPEGOPTIM.tar.gz" 661a808dfffa933d78c6beb47a2937d572b9f03e94cbaaab3d4c0d72f410e9be)"
(cd "jpegoptim-$JPEGOPTIM" && ./configure LDFLAGS=-static && make && cp jpegoptim "$OUT/")

# pngquant 2.18, the last C version; the source tarball ships libimagequant in lib/. No lcms2.
tar -xzf "$(fetch "https://pngquant.org/pngquant-$PNGQUANT-src.tar.gz" e72194b52b36f040deaec49a1ddd5dcd8d4feecc3a5fe6c5e9589a9707b233d4)"
(cd "pngquant-$PNGQUANT" && ./configure --without-cocoa --without-lcms2 --extra-ldflags=-static && make && cp pngquant "$OUT/")

# gifsicle
tar -xzf "$(fetch "https://www.lcdf.org/gifsicle/gifsicle-$GIFSICLE.tar.gz" fd23d279681a6dfe3c15264e33f344045b3ba473da4d19f49e67a50994b077fb)"
(cd "gifsicle-$GIFSICLE" && ./configure --disable-gifview --disable-gifdiff LDFLAGS=-static && make && cp src/gifsicle "$OUT/")

# optipng, with the system zlib and libpng
tar -xzf "$(fetch "https://downloads.sourceforge.net/project/optipng/OptiPNG/optipng-$OPTIPNG/optipng-$OPTIPNG.tar.gz" 25a3bd68481f21502ccaa0f4c13f84dcf6b20338e4c4e8c51f2cefbd8513398c)"
(cd "optipng-$OPTIPNG" && ./configure --with-system-zlib --with-system-libpng && make LDFLAGS="-static -s" && cp src/optipng/optipng "$OUT/")

# cwebp: Google's own static build
case "$ARCH" in
    x86_64)  WEBP=x86-64;  WEBPSHA=1c5ffab71efecefa0e3c23516c3a3a1dccb45cc310ae1095c6f14ae268e38067 ;;
    aarch64) WEBP=aarch64; WEBPSHA=69f5eebe203e0f3942fe37986209a1725741be19c152950a4283b376c95ec798 ;;
    *) echo "No cwebp for $ARCH"; exit 1 ;;
esac
tar -xzf "$(fetch "https://storage.googleapis.com/downloads.webmproject.org/releases/webp/libwebp-$LIBWEBP-linux-$WEBP.tar.gz" "$WEBPSHA")"
cp "libwebp-$LIBWEBP-linux-$WEBP/bin/cwebp" "$OUT/"

strip "$OUT"/*
chmod 755 "$OUT"/*

# Every binary must be static and must run
for tool in jpegoptim pngquant gifsicle optipng cwebp; do
    file "$OUT/$tool" | grep -qE 'static(ally|-pie) linked' || { echo "$tool is not static:"; file "$OUT/$tool"; exit 1; }
done

"$OUT/jpegoptim" --version | head -1
"$OUT/pngquant" --version
"$OUT/gifsicle" --version | head -1
"$OUT/optipng" -version | head -1
"$OUT/cwebp" -version

ls -la "$OUT"
