export function useBytes() {
    function getBytes(size) {
        const sign = size < 0 ? '-' : '';
        size = Math.abs(size) || 0;
        let i = (size == 0) ? 0 : Math.floor(Math.log(size) / Math.log(1024));
        return sign + (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + ['B', 'kB', 'MB', 'GB', 'TB'][i];
    }

    return { getBytes };
}
