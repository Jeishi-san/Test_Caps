import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { downloadBlobWithHeaders, readBlobErrorMessage } from '@/utils/download';

export interface StegoDecodeDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    status?: 'pending' | 'ready' | 'failed';
    segments_count: number;
    created_at: string;
    decoding_status?: string;
    decoding_error?: string;
    download_path?: string;
}

interface UseStegoDecodeOptions {
    initialDocs: StegoDecodeDoc[];
    pollIntervalMs?: number;
    unauthorizedMessage?: string;
    suppressUnauthorizedError?: boolean;
    onUnauthorized?: () => void;
}

interface UseStegoDecodeResult {
    docs: StegoDecodeDoc[];
    setDocs: React.Dispatch<React.SetStateAction<StegoDecodeDoc[]>>;
    selected: string;
    setSelected: React.Dispatch<React.SetStateAction<string>>;
    decodingStatus: string | null;
    isSubmitting: boolean;
    isDownloading: boolean;
    submitError: string;
    setSubmitError: React.Dispatch<React.SetStateAction<string>>;
    startDecode: () => Promise<void>;
    downloadDecoded: () => Promise<void>;
}

async function resolveAxiosMessage(error: unknown, fallback: string): Promise<string> {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data;

        if (typeof data?.message === 'string' && data.message.trim() !== '') {
            return data.message;
        }

        if (data instanceof Blob) {
            const blobMessage = await readBlobErrorMessage(data);
            if (blobMessage) {
                return blobMessage;
            }
        }
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    return fallback;
}

export function useStegoDecode(options: UseStegoDecodeOptions): UseStegoDecodeResult {
    const {
        initialDocs,
        pollIntervalMs = 2000,
        unauthorizedMessage = 'Session expired. Please log in again.',
        suppressUnauthorizedError = false,
        onUnauthorized,
    } = options;

    const [docs, setDocs] = useState<StegoDecodeDoc[]>(initialDocs);
    const [selected, setSelected] = useState('');
    const [decodingStatus, setDecodingStatus] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDownloading, setIsDownloading] = useState(false);
    const [submitError, setSubmitError] = useState('');

    useEffect(() => {
        setDocs(initialDocs);
    }, [initialDocs]);

    const selectedDoc = useMemo(
        () => docs.find((doc) => String(doc.id) === selected),
        [docs, selected]
    );

    const applyDecodeStatus = (status: string, error?: string, downloadPath?: string) => {
        if (!selected) return;

        setDocs((prevDocs) =>
            prevDocs.map((doc) =>
                doc.id === parseInt(selected, 10)
                    ? {
                          ...doc,
                          decoding_status: status,
                          decoding_error: error,
                          download_path: downloadPath,
                      }
                    : doc
            )
        );

        setDecodingStatus(status);
        if (status === 'failed' && error) {
            setSubmitError(error);
        }
    };

    const markUnauthorized = () => {
        onUnauthorized?.();
        if (!suppressUnauthorizedError) {
            setSubmitError(unauthorizedMessage);
        }
    };

    const checkDecodingStatus = async () => {
        if (!selected) return;

        try {
            const response = await axios.get(`/api/stego/documents/${selected}/status`);
            const statusData = response.data;

            if (!statusData || typeof statusData !== 'object' || !statusData.status) {
                throw new Error('Invalid status response from server.');
            }

            applyDecodeStatus(statusData.status as string, statusData.error, statusData.download_path);
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) {
                markUnauthorized();
                return;
            }

            setSubmitError(await resolveAxiosMessage(error, 'Failed to check decoding status.'));
        }
    };

    useEffect(() => {
        if (!decodingStatus || !['pending', 'in_progress'].includes(decodingStatus)) {
            return;
        }

        const interval = window.setInterval(() => {
            void checkDecodingStatus();
        }, pollIntervalMs);

        return () => window.clearInterval(interval);
    }, [decodingStatus, pollIntervalMs, selected]);

    const startDecode = async () => {
        if (!selected || isSubmitting) return;

        setSubmitError('');
        setIsSubmitting(true);

        try {
            const response = await axios.post('/api/stego/decode', {
                stego_document_id: Number(selected),
            });

            if (!response || (response.status !== 200 && response.status !== 202)) {
                throw new Error('Decode request was not accepted by the server.');
            }

            applyDecodeStatus('pending');
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) {
                markUnauthorized();
            } else {
                setSubmitError(await resolveAxiosMessage(error, 'Decode failed. Please try again.'));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const downloadDecoded = async () => {
        if (!selected || isDownloading) return;

        setIsDownloading(true);

        try {
            const response = await axios.get(`/api/stego/decode/${selected}`, {
                responseType: 'blob',
            });

            const fallbackFilename =
                selectedDoc?.document?.name && selectedDoc?.document?.extension
                    ? `${selectedDoc.document.name}.${selectedDoc.document.extension}`
                    : 'decoded_file';

            await downloadBlobWithHeaders({
                blob: response.data,
                contentType: response.headers['content-type'],
                contentDisposition: response.headers['content-disposition'],
                fallbackFilename,
            });
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) {
                markUnauthorized();
            } else {
                setSubmitError(await resolveAxiosMessage(error, 'Download failed. Please try again.'));
            }
        } finally {
            setIsDownloading(false);
        }
    };

    return {
        docs,
        setDocs,
        selected,
        setSelected,
        decodingStatus,
        isSubmitting,
        isDownloading,
        submitError,
        setSubmitError,
        startDecode,
        downloadDecoded,
    };
}
