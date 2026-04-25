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
  Unlock,
  Shield
} from 'lucide-react';
import type { DocumentEntity } from '@/types/entities';
import { formatFileSize } from '@/utils/fileSize';
import Dropdown from '@/Components/Dropdown';

interface DocumentCardProps {
  document: DocumentEntity;
  onPreview: (document: DocumentEntity) => void;
  onDownload: (document: DocumentEntity) => void;
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
  onDownload,
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
    let bgColor = 'bg-gray-100';
    let textColor = 'text-gray-600';
    
    if (ext === 'pdf') { bgColor = 'bg-red-100'; textColor = 'text-red-600'; }
    else if (['doc', 'docx'].includes(ext)) { bgColor = 'bg-blue-100'; textColor = 'text-blue-600'; }
    else if (['txt'].includes(ext)) { bgColor = 'bg-gray-100'; textColor = 'text-gray-600'; }
    else { bgColor = 'bg-purple-100'; textColor = 'text-purple-600'; }
    
    const renderIcon = () => {
      if (ext === 'pdf' || ['doc', 'docx'].includes(ext) || ['txt'].includes(ext)) return <FileText className={`w-8 h-8 ${textColor}`} />;
      return <File className={`w-8 h-8 ${textColor}`} />;
    };

    return (
      <div className={`p-3 rounded-xl ${bgColor}`}>
        {renderIcon()}
      </div>
    );
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
      className="bg-white/80 backdrop-blur-sm rounded-2xl border border-gray-200/50 p-6 shadow-sm transition-all duration-200 hover:shadow-xl hover:border-indigo-200 group relative"
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
              onClick={() => onDownload(document)}
              className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
            >
              <Download className="w-4 h-4" />
              Download
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
       <div className="flex items-center justify-center py-6">
         {getFileIcon(document.extension)}
       </div>

       {/* Card Content */}
       <div className="px-4 pb-4">
         <h3 
           className="font-semibold text-gray-900 line-clamp-2" 
           title={document.name}
         >
           {document.name}
         </h3>
         
         <div className="flex items-center justify-between text-sm text-gray-500 mt-2">
           <span>{formatFileSize(document.size)}</span>
           <span>
             {document.created_at ? new Date(document.created_at).toLocaleDateString() : 'N/A'}
           </span>
         </div>

         {/* Secured Badge */}
         {document.is_stegoed && (
           <div className="mt-3 inline-flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-2.5 py-1">
             <Shield className="w-4 h-4 text-green-600" />
             <span className="text-xs font-medium text-green-700">Secured</span>
           </div>
         )}

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

