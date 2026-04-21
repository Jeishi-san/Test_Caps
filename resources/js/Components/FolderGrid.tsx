import { Folder, FolderOpen, Plus } from 'lucide-react';

export type FolderGridItem = {
    id: number;
    name: string;
    documentCount: number;
    description?: string;
};

type FolderGridProps = {
    folders: FolderGridItem[];
    viewMode?: 'grid' | 'compact';
    onSelectFolder?: (folder: FolderGridItem) => void;
    onNewFolderClick?: () => void;
};

export default function FolderGrid({ folders, viewMode = 'grid', onSelectFolder, onNewFolderClick }: FolderGridProps) {
    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-xl font-semibold text-slate-950">Folders</h3>
                    <p className="text-sm text-slate-500">Browse collections and jump into focused views quickly.</p>
                </div>
                <button
                    type="button"
                    onClick={onNewFolderClick}
                    className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                >
                    <Plus className="size-4" />
                    New folder
                </button>
            </div>

            <div className={viewMode === 'compact' ? 'space-y-2' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'}>
                {folders.map((folder) => (
                    <button
                        key={folder.id}
                        type="button"
                        onClick={() => onSelectFolder?.(folder)}
                        className="group rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-lg"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <div className="inline-flex rounded-xl bg-cyan-50 p-2 text-cyan-700">
                                    {folder.documentCount > 0 ? <FolderOpen className="size-5" /> : <Folder className="size-5" />}
                                </div>
                                <h4 className="mt-3 font-semibold text-slate-900">{folder.name}</h4>
                            </div>
                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                {folder.documentCount} files
                            </span>
                        </div>

                        {folder.description && <p className="mt-3 text-sm leading-6 text-slate-500">{folder.description}</p>}
                    </button>
                ))}
            </div>
        </section>
    );
}