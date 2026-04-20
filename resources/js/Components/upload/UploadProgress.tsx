interface UploadProgressProps {
    progressPercent: number;
    activeFileLabel: string;
}

export default function UploadProgress({ progressPercent, activeFileLabel }: UploadProgressProps) {
    return (
        <div className="mt-4 rounded border border-blue-200 bg-blue-50 p-3">
            <p className="text-sm text-blue-700">
                Uploading {activeFileLabel !== '' ? activeFileLabel : 'file'}...
            </p>
            <div className="mt-2 h-2 w-full overflow-hidden rounded bg-blue-100">
                <div
                    className="h-2 bg-blue-500 transition-all"
                    style={{ width: `${progressPercent}%` }}
                />
            </div>
            <p className="mt-1 text-xs text-blue-700">{progressPercent}%</p>
        </div>
    );
}
