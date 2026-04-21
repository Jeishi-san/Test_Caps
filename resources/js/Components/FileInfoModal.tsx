import Modal from '@/Components/Modal';
import { formatFileSize } from '@/utils/fileSize';
import { CalendarDays, Download, Hash, Link2, User } from 'lucide-react';

export type FileInfoDocument = {
    id: number;
    name: string;
    extension?: string;
    size: number;
    created_at: string;
    file_path?: string;
    owner?: string;
};

type FileInfoModalProps = {
    document: FileInfoDocument | null;
    show: boolean;
    onClose: () => void;
    currentUserEmail?: string;
};

export default function FileInfoModal({ document, show, onClose, currentUserEmail = 'user@example.com' }: FileInfoModalProps) {
    if (!document) {
        return null;
    }

    return (
        <Modal show={show} onClose={onClose} maxWidth="3xl" title={`File details: ${document.name}`}>
            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <InfoCard icon={Hash} label="Document ID" value={String(document.id)} />
                    <InfoCard icon={Download} label="Size" value={formatFileSize(document.size, { decimals: 2, longBytesLabel: false })} />
                    <InfoCard icon={CalendarDays} label="Created" value={new Date(document.created_at).toLocaleString()} />
                    <InfoCard icon={User} label="Owner" value={document.owner ?? currentUserEmail} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <Link2 className="size-4 text-cyan-600" />
                        Storage path
                    </div>
                    <p className="mt-2 break-all text-sm text-slate-600">
                        {document.file_path ?? 'No direct storage path is available for this record.'}
                    </p>
                </div>
            </div>
        </Modal>
    );
}

function InfoCard({ icon: Icon, label, value }: { icon: typeof Hash; label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 text-sm font-medium text-slate-500">
                <Icon className="size-4 text-cyan-600" />
                {label}
            </div>
            <p className="mt-2 text-sm font-semibold text-slate-950">{value}</p>
        </div>
    );
}