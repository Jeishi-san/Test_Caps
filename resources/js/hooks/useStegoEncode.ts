import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { carrierCapacity } from '@/utils/carrierCalculations';

export interface EncodeCarrierInfo {
    file: File;
    width?: number;
    height?: number;
    capacity?: number;
    loading: boolean;
}

export interface QualityMetric {
    carrier: string;
    psnr: number | null;
    threshold_40db: boolean;
}

interface StegoEncodeStatusResponse {
    id: number;
    status: 'pending' | 'ready' | 'failed';
    failed_reason?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

interface UseStegoEncodeOptions {
    initialStep?: 1 | 2 | 3;
    pollIntervalMs?: number;
}

interface SubmitEncodeOptions {
    documentId: string;
    useSystemCarriers?: boolean;
    endpoint?: string;
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error) && typeof error.response?.data?.message === 'string') {
        return error.response.data.message;
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    return fallback;
}

export function selectCarriersForCapacity(
    source: EncodeCarrierInfo[],
    requiredBytes: number
): EncodeCarrierInfo[] {
    const sorted = [...source]
        .filter((carrier) => (carrier.capacity ?? 0) > 0)
        .sort((a, b) => (b.capacity ?? 0) - (a.capacity ?? 0));

    const selected: EncodeCarrierInfo[] = [];
    let accumulatedCapacity = 0;

    for (const carrier of sorted) {
        if (accumulatedCapacity >= requiredBytes) break;
        selected.push(carrier);
        accumulatedCapacity += carrier.capacity ?? 0;
    }

    return selected;
}

export function useStegoEncode(options: UseStegoEncodeOptions = {}) {
    const { initialStep = 1, pollIntervalMs = 1500 } = options;

    const [step, setStep] = useState<1 | 2 | 3>(initialStep);
    const [carriers, setCarriers] = useState<EncodeCarrierInfo[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [qualityMetrics, setQualityMetrics] = useState<QualityMetric[]>([]);
    const [stegoDocumentId, setStegoDocumentId] = useState<number | null>(null);
    const [encodeStatus, setEncodeStatus] = useState<'idle' | 'pending' | 'ready' | 'failed'>('idle');
    const [encodeElapsedSeconds, setEncodeElapsedSeconds] = useState<number | null>(null);
    const [encodeProcessingTimeSeconds, setEncodeProcessingTimeSeconds] = useState<number | null>(null);
    const [encodeFailedReason, setEncodeFailedReason] = useState<string | null>(null);
    const [encodeQueuedMessage, setEncodeQueuedMessage] = useState<string | null>(null);
    const [selectedCarriers, setSelectedCarriers] = useState<EncodeCarrierInfo[]>([]);
    const [isSelectingCarriers, setIsSelectingCarriers] = useState(false);
    const [useSystemCarriers, setUseSystemCarriers] = useState(false);

    const pollTimerRef = useRef<number | null>(null);

    useEffect(() => {
        return () => {
            if (pollTimerRef.current !== null) {
                window.clearInterval(pollTimerRef.current);
                pollTimerRef.current = null;
            }
        };
    }, []);

    const formatSeconds = (seconds: number | null): string => {
        if (seconds === null || !Number.isFinite(seconds)) return '-';
        if (seconds < 60) return `${seconds.toFixed(2)}s`;
        const mins = Math.floor(seconds / 60);
        const rem = seconds % 60;
        return `${mins}m ${rem.toFixed(1)}s`;
    };

    const deriveSeconds = (createdAt?: string | null, updatedAt?: string | null): number | null => {
        if (!createdAt || !updatedAt) return null;
        const created = new Date(createdAt).getTime();
        const updated = new Date(updatedAt).getTime();
        if (!Number.isFinite(created) || !Number.isFinite(updated) || updated < created) return null;
        return (updated - created) / 1000;
    };

    const deriveElapsedNow = (createdAt?: string | null): number | null => {
        if (!createdAt) return null;
        const created = new Date(createdAt).getTime();
        if (!Number.isFinite(created)) return null;
        return Math.max(0, (Date.now() - created) / 1000);
    };

    const stopPolling = () => {
        if (pollTimerRef.current !== null) {
            window.clearInterval(pollTimerRef.current);
            pollTimerRef.current = null;
        }
    };

    const pollEncodeStatus = async (id: number) => {
        try {
            const response = await axios.get<StegoEncodeStatusResponse>(`/api/stego/documents/${id}`);
            const payload = response.data;

            setEncodeStatus(payload.status);

            if (payload.status === 'ready' || payload.status === 'failed') {
                const duration = deriveSeconds(payload.created_at, payload.updated_at);
                setEncodeProcessingTimeSeconds(duration);
                setEncodeElapsedSeconds(duration);
                setEncodeFailedReason(payload.failed_reason ?? null);
                stopPolling();
                return;
            }

            setEncodeElapsedSeconds(deriveElapsedNow(payload.created_at));
        } catch (error: unknown) {
            stopPolling();
            setEncodeStatus('failed');
            setEncodeFailedReason(getErrorMessage(error, 'Failed to fetch encoding status.'));
        }
    };

    const startPolling = (id: number) => {
        stopPolling();
        void pollEncodeStatus(id);
        pollTimerRef.current = window.setInterval(() => {
            void pollEncodeStatus(id);
        }, pollIntervalMs);
    };

    const removeCarrier = (index: number) => {
        setCarriers((prev) => prev.filter((_, i) => i !== index));
    };

    const addCarrierFiles = (files: FileList | null) => {
        if (!files) return;

        const validFiles = Array.from(files).filter((file) =>
            /\.(png|bmp|jpe?g)$/i.test(file.name) && file.size <= 100 * 1024 * 1024
        );

        const invalidFiles = Array.from(files).filter((file) =>
            !/\.(png|bmp|jpe?g)$/i.test(file.name) || file.size > 100 * 1024 * 1024
        );

        if (invalidFiles.length > 0) {
            const messages: string[] = [];
            const invalidTypes = invalidFiles.filter((f) => !/\.(png|bmp|jpe?g)$/i.test(f.name));
            const oversizedFiles = invalidFiles.filter((f) => f.size > 100 * 1024 * 1024);

            if (invalidTypes.length > 0) {
                messages.push(`Invalid type(s): ${invalidTypes.map((f) => f.name).join(', ')} (only PNG/BMP/JPEG)`);
            }
            if (oversizedFiles.length > 0) {
                messages.push(`Too large: ${oversizedFiles.map((f) => f.name).join(', ')} (max 100 MB)`);
            }

            setErrors((prev) => ({ ...prev, carriers: messages.join('. ') }));
        }

        if (validFiles.length === 0) return;

        const newEntries: EncodeCarrierInfo[] = validFiles.map((file) => ({ file, loading: true }));
        setCarriers((prev) => [...prev, ...newEntries]);

        validFiles.forEach((file) => {
            const imageUrl = URL.createObjectURL(file);
            const image = new Image();
            image.onload = () => {
                const cap = carrierCapacity(image.naturalWidth, image.naturalHeight);
                URL.revokeObjectURL(imageUrl);
                setCarriers((prev) =>
                    prev.map((carrier) =>
                        carrier.file === file
                            ? {
                                  ...carrier,
                                  width: image.naturalWidth,
                                  height: image.naturalHeight,
                                  capacity: cap,
                                  loading: false,
                              }
                            : carrier
                    )
                );
            };
            image.onerror = () => {
                URL.revokeObjectURL(imageUrl);
                setCarriers((prev) =>
                    prev.map((carrier) =>
                        carrier.file === file ? { ...carrier, loading: false, capacity: 0 } : carrier
                    )
                );
            };
            image.src = imageUrl;
        });
    };

    const runCarrierSelection = (requiredBytes: number, source = carriers) => {
        setIsSelectingCarriers(true);
        window.setTimeout(() => {
            setSelectedCarriers(selectCarriersForCapacity(source, requiredBytes));
            setIsSelectingCarriers(false);
        }, 500);
    };

    const submitEncode = async ({
        documentId,
        useSystemCarriers: useSystem = false,
        endpoint = '/api/stego/encode',
    }: SubmitEncodeOptions) => {
        setErrors({});
        setLoading(true);

        const formData = new FormData();
        formData.append('document_id', documentId);
        if (useSystem) {
            formData.append('use_system_carriers', '1');
        }
        carriers.forEach((carrier) => formData.append('carriers[]', carrier.file));

        try {
            const response = await axios.post(endpoint, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const queuedId = Number(response.data?.stego_document_id ?? 0);
            setStegoDocumentId(Number.isFinite(queuedId) && queuedId > 0 ? queuedId : null);
            setQualityMetrics(response.data.quality_metrics ?? []);
            setEncodeStatus('pending');
            setEncodeElapsedSeconds(0);
            setEncodeProcessingTimeSeconds(null);
            setEncodeFailedReason(null);
            setEncodeQueuedMessage(response.data?.message ?? 'Encoding queued. Waiting for completion...');

            if (Number.isFinite(queuedId) && queuedId > 0) {
                startPolling(queuedId);
            }

            setStep(3);
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 401) {
                setErrors({ session: error.response.data?.message ?? 'Session expired. Please log in again.' });
            } else if (axios.isAxiosError(error)) {
                setErrors(error.response?.data?.errors ?? { encode: error.response?.data?.message ?? 'Encoding failed.' });
            } else {
                setErrors({ encode: getErrorMessage(error, 'Encoding failed.') });
            }
            stopPolling();
            setStep(2);
        } finally {
            setLoading(false);
        }
    };

    const allCarriersLoaded = carriers.length > 0 && carriers.every((carrier) => !carrier.loading);

    return {
        step,
        setStep,
        carriers,
        setCarriers,
        dragOver,
        setDragOver,
        loading,
        setLoading,
        errors,
        setErrors,
        qualityMetrics,
        stegoDocumentId,
        encodeStatus,
        encodeElapsedSeconds,
        encodeProcessingTimeSeconds,
        encodeFailedReason,
        encodeQueuedMessage,
        selectedCarriers,
        setSelectedCarriers,
        isSelectingCarriers,
        useSystemCarriers,
        setUseSystemCarriers,
        formatSeconds,
        addCarrierFiles,
        removeCarrier,
        runCarrierSelection,
        submitEncode,
        stopPolling,
        allCarriersLoaded,
    };
}
