import { ChangeEvent, DragEvent, useMemo, useState } from 'react';
import { CheckCircle2, FileText, Upload, X } from 'lucide-react';

type UploadAreaProps = {
    onUpload?: (file: File | null) => void;
    onCancel?: () => void;
};

export default function UploadArea({ onUpload, onCancel }: UploadAreaProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);

    const fileSummary = useMemo(() => {
        if (!selectedFile) {
            return 'Drop a file here or choose one from your device.';
        }

        return `${selectedFile.name} • ${(selectedFile.size / 1024 / 1024).toFixed(2)} MB`;
    }, [selectedFile]);

    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        setSelectedFile(event.target.files?.[0] ?? null);
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        setSelectedFile(event.dataTransfer.files?.[0] ?? null);
    };

    return (
        <section
            className={`rounded-3xl border border-dashed p-6 transition ${isDragging ? 'border-cyan-400 bg-cyan-50' : 'border-slate-300 bg-white'}`}
            onDragOver={(event) => {
                event.preventDefault();
                setIsDragging(true);
            }}
            onDragLeave={() => setIsDragging(false)}
            onDrop={handleDrop}
        >
            <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div className="max-w-2xl space-y-3">
                    <div className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        <Upload className="size-3.5" />
                        Upload area
                    </div>
                    <div>
                        <h3 className="text-2xl font-semibold text-slate-950">Secure file intake</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Choose a file to start a secure upload flow. The source module used a modal-heavy workflow;
                            this version keeps the interaction compact while preserving the same intent.
                        </p>
                    </div>
                    <div className="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <FileText className="size-4 text-cyan-600" />
                        <span>{fileSummary}</span>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 lg:min-w-[260px] lg:justify-end">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        <Upload className="size-4" />
                        Select file
                        <input type="file" className="hidden" onChange={handleFileChange} />
                    </label>

                    <button
                        type="button"
                        onClick={() => onUpload?.(selectedFile)}
                        disabled={!selectedFile}
                        className="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:bg-cyan-300"
                    >
                        <CheckCircle2 className="size-4" />
                        Upload
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setSelectedFile(null);
                            onCancel?.();
                        }}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        <X className="size-4" />
                        Cancel
                    </button>
                </div>
            </div>
        </section>
    );
}