import { useState } from 'react';
import type { DocumentEntity } from '@/types/entities';
import { formatFileSize } from '@/utils/fileSize';
import {
  FileText,
  File,
  Image as ImageIcon,
  Film,
  Music,
  Archive,
  Shield,
  Eye,
  Download,
  Share2,
  Edit3,
  Trash2,
  FolderOpen,
  Info,
  Unlock,
  Star
} from 'lucide-react';
import Dropdown from '@/Components/Dropdown';

interface DocumentListProps {
  documents: DocumentEntity[];
  onPreview: (document: DocumentEntity) => void;
  onUnlock: (document: DocumentEntity) => void;
  onShare: (document: DocumentEntity) => void;
  onRename: (document: DocumentEntity) => void;
  onMove: (document: DocumentEntity) => void;
  onDelete: (document: DocumentEntity) => void;
  onInfo: (document: DocumentEntity) => void;
  onToggleStar: (documentId: number) => Promise<void>;
}

const getFileIcon = (extension: string) => {
  const ext = extension?.toLowerCase();
  let bgColor = 'bg-gray-100';
  let textColor = 'text-gray-600';

  if (ext === 'pdf') { bgColor = 'bg-red-100'; textColor = 'text-red-600'; }
  else if (['doc', 'docx'].includes(ext)) { bgColor = 'bg-blue-100'; textColor = 'text-blue-600'; }
  else if (['txt'].includes(ext)) { bgColor = 'bg-gray-100'; textColor = 'text-gray-600'; }
  else { bgColor = 'bg-purple-100'; textColor = 'text-purple-600'; }

  const renderIcon = () => {
    if (ext === 'pdf' || ['doc', 'docx'].includes(ext) || ['txt'].includes(ext)) return <FileText className={`w-5 h-5 ${textColor}`} />;
    return <File className={`w-5 h-5 ${textColor}`} />;
  };

  return (
    <div className={`p-2 rounded-lg ${bgColor}`}>
      {renderIcon()}
    </div>
  );
};

export default function DocumentList({
  documents,
  onPreview,
  onUnlock,
  onShare,
  onRename,
  onMove,
  onDelete,
  onInfo,
  onToggleStar,
}: DocumentListProps) {
  const [processingDocuments, setProcessingDocuments] = useState<Record<number, string>>({});

  return (
    <div className="bg-white/80 backdrop-blur-sm rounded-2xl border border-gray-200/50 overflow-hidden">
      {/* Header Row */}
      <div className="bg-gray-50/50 border-b border-gray-200 px-6 py-4 flex text-xs font-semibold text-gray-700 uppercase">
        <div className="flex-1">Name</div>
        <div className="w-32">Owner</div>
        <div className="w-24">Size</div>
        <div className="w-32">Status</div>
        <div className="w-32">Modified</div>
        <div className="w-20">Actions</div>
      </div>

      {/* Data Rows */}
      {documents.map((document) => (
        <div key={document.id} className="flex items-center px-6 py-4 border-b border-gray-200 hover:bg-gray-50/50 divide-y divide-gray-200">
          {/* Name Cell */}
          <div className="flex-1 flex items-center gap-3">
            {getFileIcon(document.extension)}
            <div>
              <p className="font-medium text-gray-900 truncate max-w-xs">{document.name}</p>
              <p className="text-sm text-gray-500">{document.extension?.toUpperCase()}</p>
            </div>
          </div>

          {/* Owner Cell */}
          <div className="w-32 text-sm text-gray-700 truncate">
            You
          </div>

          {/* Size Cell */}
          <div className="w-24 text-sm text-gray-500">
            {formatFileSize(document.size)}
          </div>

          {/* Status Cell */}
          <div className="w-32">
            {document.is_stegoed ? (
              <div className="inline-flex items-center gap-1 bg-green-50 text-green-700 border border-green-200 rounded-lg px-2.5 py-1 text-xs font-medium">
                <Shield className="w-3.5 h-3.5" />
                Secured
              </div>
            ) : (
              <span className="text-sm text-gray-500">Original</span>
            )}
          </div>

          {/* Modified Cell */}
          <div className="w-32 text-sm text-gray-500">
            {document.created_at ? new Date(document.created_at).toLocaleDateString() : 'N/A'}
          </div>

          {/* Actions Cell */}
          <div className="w-20">
            <Dropdown>
              <Dropdown.Trigger>
                <button className="p-1.5 rounded-lg hover:bg-gray-100">
                  <Star className="w-4 h-4 text-gray-500" />
                </button>
              </Dropdown.Trigger>
              <Dropdown.Content align="right" width="48">
                <button
                  onClick={() => onPreview(document)}
                  className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <Eye className="w-4 h-4" />
                  Preview
                </button>
                <button
                  onClick={() => onUnlock(document)}
                  disabled={!document.is_stegoed}
                  className={`w-full px-4 py-2 text-left text-sm flex items-center gap-3 ${
                    document.is_stegoed ? 'text-indigo-600 hover:bg-indigo-50' : 'text-gray-400 cursor-not-allowed'
                  }`}
                >
                  <Unlock className="w-4 h-4" />
                  Unlock
                </button>
                <button
                  onClick={() => onShare(document)}
                  className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <Share2 className="w-4 h-4" />
                  Share
                </button>
                <button
                  onClick={() => onRename(document)}
                  className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <Edit3 className="w-4 h-4" />
                  Rename
                </button>
                <button
                  onClick={() => onMove(document)}
                  className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <FolderOpen className="w-4 h-4" />
                  Move
                </button>
                <button
                  onClick={() => onInfo(document)}
                  className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                >
                  <Info className="w-4 h-4" />
                  Info
                </button>
                <div className="border-t border-gray-100 my-1" />
                <button
                  onClick={() => onDelete(document)}
                  className="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                >
                  <Trash2 className="w-4 h-4" />
                  Delete
                </button>
              </Dropdown.Content>
            </Dropdown>
          </div>
        </div>
      ))}

      {/* Empty State */}
      {documents.length === 0 && (
        <div className="py-12 text-center">
          <p className="text-sm text-gray-500">No documents found</p>
        </div>
      )}
    </div>
  );
}
