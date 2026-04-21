export function parseDownloadFilename(contentDisposition?: string, fallback = 'downloaded_file'): string {
    if (!contentDisposition) return fallback;

    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1]);
    }

    const quotedMatch = contentDisposition.match(/filename="([^"]+)"/i);
    if (quotedMatch?.[1]) {
        return quotedMatch[1];
    }

    const plainMatch = contentDisposition.match(/filename=([^;]+)/i);
    return plainMatch?.[1]?.trim() || fallback;
}

export async function readBlobErrorMessage(blob: Blob): Promise<string | null> {
    try {
        const text = await blob.text();
        if (!text) return null;

        try {
            const parsed = JSON.parse(text) as { message?: string };
            if (typeof parsed.message === 'string' && parsed.message.trim() !== '') {
                return parsed.message;
            }
        } catch {
            // Fallback to plain text response when JSON parsing fails.
        }

        return text;
    } catch {
        return null;
    }
}

export async function downloadBlobWithHeaders(params: {
    blob: Blob;
    contentType?: string;
    contentDisposition?: string;
    fallbackFilename?: string;
}): Promise<void> {
    const {
        blob,
        contentType,
        contentDisposition,
        fallbackFilename = 'downloaded_file',
    } = params;

    if ((contentType || '').toLowerCase().includes('application/json')) {
        const message = (await readBlobErrorMessage(blob)) || 'Download failed.';
        throw new Error(message);
    }

    const filename = parseDownloadFilename(contentDisposition, fallbackFilename);
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(blobUrl);
}
