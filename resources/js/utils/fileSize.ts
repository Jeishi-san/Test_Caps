interface FormatFileSizeOptions {
    decimals?: number;
    longBytesLabel?: boolean;
}

export function formatFileSize(bytes: number, options: FormatFileSizeOptions = {}): string {
    const { decimals = 2, longBytesLabel = true } = options;

    if (bytes === 0) {
        return longBytesLabel ? '0 Bytes' : '0 B';
    }

    const k = 1024;
    const sizes = longBytesLabel
        ? ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB']
        : ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(k)), sizes.length - 1);
    const value = bytes / Math.pow(k, i);

    return `${value.toFixed(decimals).replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1')} ${sizes[i]}`;
}