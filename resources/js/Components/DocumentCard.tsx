import { useState } from 'react';
import {
  FileText,
  File,
  Image as ImageIcon,
  Film,
  Music,
  Archive,
  Star,
  MoreVertical,
  Eye,
  Download,
  Share2,
  Edit3,
  Trash2,
  Lock,
  Loader2,
  FolderOpen,
  Info,
  Unlock
} from 'lucide-react';
import type { DocumentEntity } from '@/types/entities';
import { formatFileSize } from '@/utils/fileSize';
import Dropdown from '@/Components/Dropdown';

interface DocumentCardProps {
  document: DocumentEntity;
  onPreview: (document: DocumentEntity) => void;
  onUnlock: (document: DocumentEntity) => void;
  onShare: (document: DocumentEntity) => void;
  onRename: (document: DocumentEntity) => void;
  onMove: (document: DocumentEntity) => void;
  onDelete: (document: DocumentEntity) => void;
  onInfo: (document: DocumentEntity) => void;
  onToggleStar: (documentId: number) => Promise<void>;
  isProcessing?: boolean;
  processingStatus?: string;
  isWatched?: boolean;
}

export default function DocumentCard({
  document,
  onPreview,
  onUnlock,
  onShare,
  onRename,
  onMove,
  onDelete,
  onInfo,
  onToggleStar,
  isProcessing = false,
  processingStatus = '',
  isWatched = false,
}: DocumentCardProps) {
  const [isHovered, setIsHovered] = useState(false);
  const [isStarring, setIsStarring] = useState(false);

  const getFileIcon = (extension: string) => {
    const ext = extension?.toLowerCase();
    
    if (ext === 'pdf') return <FileText className="w-8 h-8 text-red-500" />;
    if (['doc', 'docx'].includes(ext)) return <FileText className="w-8 h-8 text-blue-600" />;
    if (['xls', 'xlsx'].includes(ext)) return <FileText className="w-8 h-8 text-green-600" />;
    if (['ppt', 'pptx'].includes(ext)) return <FileText className="w-8 h-8 text-orange-500" />;
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return <ImageIcon className="w-8 h-8 text-purple-500" />;
    if (['mp4', 'webm', 'mov'].includes(ext)) return <Film className="w-8 h-8 text-indigo-500" />;
    if (['mp3', 'wav', 'flac'].includes(ext)) return <Music className="w-8 h-8 text-pink-500" />;
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return <Archive className="w-8 h-8 text-amber-600" />;
    
    return <File className="w-8 h-8 text-gray-500" />;
  };

  const handleStarClick = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (isStarring) return;
    
    setIsStarring(true);
    await onToggleStar(document.id);
    setIsStarring(false);
  };

  return (
    <div
      className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 group relative"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {/* Processing Overlay */}
      {isProcessing && (
        <div className="absolute inset-0 bg-white/90 z-20 flex flex-col items-center justify-center gap-3">
          <Loader2 className="w-10 h-10 text-indigo-500 animate-spin" />
          <p className="text-sm font-medium text-gray-700">{processingStatus}</p>
        </div>
      )}

      {/* Card Header Actions */}
      <div className="absolute top-3 right-3 z-10 flex items-center gap-1">
        <button
          onClick={handleStarClick}
          disabled={isStarring}
          className={`p-1.5 rounded-lg transition-all duration-200 ${
            isHovered || document.is_starred
              ? 'opacity-100 bg-white/80 hover:bg-amber-50'
              : 'opacity-0 bg-transparent'
          } ${document.is_starred ? 'text-amber-500' : 'text-gray-400 hover:text-amber-500'} ${isStarring ? 'cursor-wait' : ''}`}
        >
          {isStarring ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            <Star className={`w-4 h-4 ${document.is_starred ? 'fill-current' : ''}`} />
          )}
        </button>

        <Dropdown>
          <Dropdown.Trigger>
            <button
              className={`p-1.5 rounded-lg transition-all duration-200 ${
                isHovered
                  ? 'opacity-100 bg-white/80 text-gray-600 hover:bg-gray-100'
                  : 'opacity-0 bg-transparent text-transparent'
              }`}
            >
              <MoreVertical className="w-4 h-4" />
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
                document.is_stegoed
                  ? 'text-indigo-600 hover:bg-indigo-50'
                  : 'text-gray-400 cursor-not-allowed'
              }`}
            >
              <Unlock className="w-4 h-4" />
              Unlock File
            </button>
            <button
              onClick={() => onShare(document)}
              className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
            >
              <Share2 className="w-4 h-4" />
              Share File
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
              Move File
            </button>
            <button
              onClick={() => onInfo(document)}
              className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
            >
              <Info className="w-4 h-4" />
              File Info
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

      {/* File Icon Section */}
      <div className="pt-10 pb-6 px-5 flex items-center justify-center">
        {getFileIcon(document.extension)}
      </div>

      {/* Card Content */}
      <div className="px-4 pb-4">
        <div className="flex items-center gap-1.5 mb-1">
          <h3 
            className="text-sm font-medium text-gray-900 truncate flex-1" 
            title={document.name}
          >
            {document.name}
          </h3>
          {document.is_stegoed && (
            <Lock
              className="w-3.5 h-3.5 text-indigo-500 flex-shrink-0"
              aria-label="This document has been locked with StegoLock"
            />
          )}
        </div>
        
        <div className="flex items-center justify-between text-xs text-gray-500 mt-2">
          <span>{formatFileSize(document.size)}</span>
          <span>
            {document.created_at ? new Date(document.created_at).toLocaleDateString() : 'N/A'}
          </span>
        </div>

        {/* Action Buttons Bar */}
        <div className={`mt-3 grid grid-cols-3 gap-1 transition-all duration-200 ${
          isHovered ? 'opacity-100' : 'opacity-0'
        }`}>
          <button
            onClick={handleStarClick}
            className={`py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 ${
              document.is_starred
                ? 'text-amber-500 hover:bg-amber-50'
                : 'text-gray-600 hover:bg-gray-100'
            }`}
            title={document.is_starred ? 'Remove from starred' : 'Star this document'}
          >
            <Star className={`w-3.5 h-3.5 ${document.is_starred ? 'fill-amber-500' : ''}`} />
          </button>
          <button
            onClick={() => onShare(document)}
            className="py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-100 flex items-center justify-center gap-1"
            title="Share document"
          >
            <Share2 className="w-3.5 h-3.5" />
            <span className="hidden sm:inline">Share</span>
          </button>
          <button
            className="py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-100 flex items-center justify-center"
            title="More options"
          >
            <MoreVertical className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  );
}

