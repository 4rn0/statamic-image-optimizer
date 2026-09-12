# Bundled binaries

Used as a fallback by `ImageOptimizer::findBundledBinary()` when an optimizer is not installed on the server. Layout: `linux-x86_64/`, `darwin/` (universal arm64 + x86_64), `windows/`.

## Provenance (September 2026)

| Binary | Linux x86_64 | macOS universal | Windows x64 |
| --- | --- | --- | --- |
| jpegoptim | 1.5.6, `jpegoptim-1.5.6-x64-linux.zip` from github.com/tjko/jpegoptim releases | 1.5.6, built here against IJG libjpeg 9f (static) | 1.5.6, `jpegoptim-1.5.6-x64-windows.zip` from github.com/tjko/jpegoptim releases |
| pngquant | 3.0.3 static, `pngquant-linux.tar.bz2` from pngquant.org | 2.18.0 (last C version), built here against libpng 1.6.50 (static), no lcms2 | 2.17.0, `pngquant-windows.zip` from pngquant.org |
| gifsicle | 1.92, `vendor/linux/x64/gifsicle` from github.com/imagemin/gifsicle-bin | 1.96, built here from lcdf.org source | 1.93, `vendor/win/x64/gifsicle.exe` from github.com/imagemin/gifsicle-bin |
| optipng | 0.7.5, `vendor/linux/x64/optipng` from github.com/imagemin/optipng-bin (needs libz.so.1) | 0.7.8, built here with the system zlib | 0.7.8 (32-bit), `optipng-0.7.8-win32.zip` from sourceforge.net/projects/optipng |

The Linux binaries only need glibc, libm and (optipng) zlib. The macOS binaries only link against libSystem and (optipng) the system zlib.

## Building the macOS binaries

Needs Xcode (clang, lipo). Every tool is built twice with a wrapper compiler per architecture and merged with `lipo`:

```sh
printf '#!/bin/sh\nexec clang -arch arm64 -mmacosx-version-min=11.0 "$@"\n' > cc-arm64
printf '#!/bin/sh\nexec clang -arch x86_64 -mmacosx-version-min=10.13 "$@"\n' > cc-x86_64
chmod +x cc-*
```

Per architecture `$a` (`arm64`, `x86_64`), with `CC=$PWD/cc-$a`:

```sh
# gifsicle
./configure --disable-gifview --disable-gifdiff CC=$CC CFLAGS=-O2 && make

# optipng (its bundled zlib does not compile against current SDKs)
CC=$CC CFLAGS=-O2 ./configure -with-system-zlib && make

# libjpeg 9f + jpegoptim
./configure --host=$a-apple-darwin --disable-shared --enable-static --prefix=$JPEG CC=$CC && make install
./configure --host=$a-apple-darwin --with-libjpeg=$JPEG CC=$CC CFLAGS="-O2 -I$JPEG/include" LDFLAGS="-L$JPEG/lib" && make

# libpng 1.6 + pngquant 2.18 (copy libimagequant 2.18 into pngquant/lib first, fresh per architecture).
# --without-cocoa must come before --without-lcms2 (it re-enables lcms2), and CC must be in the
# environment of make too, because the Makefile runs lib/configure itself.
./configure --host=$a-apple-darwin --disable-shared --enable-static --prefix=$PNG CC=$CC && make install
CC=$CC ./configure --without-cocoa --without-lcms2 --with-libpng=$PNG --extra-cflags="-I$PNG/include" --extra-ldflags="-L$PNG/lib" && CC=$CC make
```

Then `lipo -create -output darwin/<tool> <tool>-arm64 <tool>-x86_64` and check both slices with `<tool> --version` and `arch -x86_64 <tool> --version`.
