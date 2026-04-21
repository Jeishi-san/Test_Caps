import { useEffect, useState } from 'react';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';
import { useStegoDecode } from '@/hooks/useStegoDecode';

export default function Decode() {
    const [loadingList, setLoadingList] = useState(false);
    const [sessionExpired, setSessionExpired] = useState(false);
    const {
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
    } = useStegoDecode({
        initialDocs: [],
        suppressUnauthorizedError: true,
        onUnauthorized: () => setSessionExpired(true),
    });

    useEffect(() => {
        setLoadingList(true);
        axios
            .get('/api/stego?status=ready')
            .then((r) => setDocs(r.data.data ?? r.data))
            .catch(() => setSubmitError('Failed to load decode list.'))
            .finally(() => setLoadingList(false));
    }, []);

    const handleDecode = async () => {
        setSubmitError('');
        setSessionExpired(false);
        await startDecode();
    };

    const handleDownload = async () => {
        setSubmitError('');
        setSessionExpired(false);
        await downloadDecoded();
    };

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">🔓 Decode Document</h1>

            {/* Session Master Key banner */}
            <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span className="text-lg">🔑</span>
                <p><strong>Session Master Key active.</strong> Decryption uses the key derived from your password at login — no passphrase required.</p>
            </div>

            {sessionExpired && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    ⚠️ Your session has expired. Please <a href="/login" className="underline font-medium">log in again</a> to refresh your Master Key.
                </div>
            )}
            {submitError && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    ⚠️ {submitError}
                </div>
            )}

            {decodingStatus && (
                <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    {decodingStatus === 'pending' && '⏳ Decoding pending...'}
                    {decodingStatus === 'in_progress' && '🔄 Decoding in progress...'}
                    {decodingStatus === 'completed' && '✅ Decoding completed! Click download to get your file.'}
                    {decodingStatus === 'failed' && '❌ Decoding failed. Please try again.'}
                </div>
            )}

            <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold text-gray-800">Select stego document to decode</h2>
                {loadingList && (
                    <p className="mb-3 text-sm text-gray-500">Loading decode list...</p>
                )}
                {docs.length === 0 ? (
                    <p className="text-sm text-gray-500">No ready stego documents found. Encode one first, then wait for processing to finish.</p>
                ) : (
                    <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                        {docs.map((d) => (
                            <label key={d.id} className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 ${selected === String(d.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'}`}>
                                <input type="radio" name="stego" value={d.id} checked={selected === String(d.id)} onChange={(e) => setSelected(e.target.value)} className="h-4 w-4 text-indigo-600" />
                                <span className="text-xl">🔒</span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-800">{d.document?.name ?? '—'}</p>
                                    <p className="text-xs text-gray-400">{d.segments_count} carrier(s) · {new Date(d.created_at).toLocaleDateString()}</p>
                                    {d.decoding_status && (
                                        <p className="text-xs text-blue-600">
                                            Status: {d.decoding_status}
                                        </p>
                                    )}
                                </div>
                            </label>
                        ))}
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-2">
                    {decodingStatus === 'completed' ? (
                        <button onClick={handleDownload} disabled={!selected || isDownloading} className="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40">
                            {isDownloading ? 'Downloading…' : '📥 Download'}
                        </button>
                    ) : (
                        <button onClick={handleDecode} disabled={!selected || isSubmitting || decodingStatus === 'in_progress'} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">
                            {isSubmitting ? 'Decoding…' : '🔓 Decode'}
                        </button>
                    )}
                </div>
            </div>
        </SpaLayout>
    );
}
