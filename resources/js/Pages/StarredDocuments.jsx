export function getStatusDisplay(status) {
    const statusMap = {
        'uploaded': 'Uploaded',
        'encrypted': 'Encrypted',
        'fragmented': 'Fragmented',
        'embedded': 'Embedded',
        'error': 'Error',
        'completed': 'Completed',
    };
    return statusMap[status] || 'Unknown';
}

export function getStatusColor(status) {
    const colorMap = {
        'uploaded': 'blue',
        'encrypted': 'purple',
        'fragmented': 'yellow',
        'embedded': 'green',
        'error': 'red',
        'completed': 'gray',
    };
    return colorMap[status] || 'gray';
}

export function getFileColor(extension) {
    const colorMap = {
        'pdf': 'red',
        'doc': 'blue',
        'docx': 'blue',
        'xls': 'green',
        'xlsx': 'green',
        'png': 'purple',
        'jpg': 'purple',
        'jpeg': 'purple',
        'txt': 'gray',
    };
    return colorMap[extension?.toLowerCase()] || 'gray';
}
