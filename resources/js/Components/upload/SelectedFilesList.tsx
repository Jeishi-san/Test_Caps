interface SelectedFilesListProps {
    files: FileList;
    detailed?: boolean;
}

export default function SelectedFilesList({ files, detailed = false }: SelectedFilesListProps) {
    return (
        <div className="mt-4 rounded bg-gray-50 p-3">
            <p className="mb-2 text-sm font-medium text-gray-700">
                Selected {files.length} file{files.length !== 1 ? 's' : ''}:
            </p>

            {detailed ? (
                <ul className="max-h-32 space-y-1 overflow-y-auto text-xs text-gray-600">
                    {Array.from(files).map((file, index) => (
                        <li key={`${file.name}-${file.size}-${file.lastModified}-${index}`} className="truncate">
                            {file.name} ({(file.size / 1024).toFixed(1)} KB)
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
