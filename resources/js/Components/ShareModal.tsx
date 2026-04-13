import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import axios from 'axios';

interface ShareModalProps {
  show: boolean;
  onClose: () => void;
  documentId: number;
  documentName: string;
  slug?: string;
  onSuccess?: () => void;
}

export default function ShareModal({
  show,
  onClose,
  documentId,
  documentName,
  slug = 'document',
  onSuccess,
}: ShareModalProps) {
  const [expirationDate, setExpirationDate] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isSearchingUsers, setIsSearchingUsers] = useState(false);
  const [shareLink, setShareLink] = useState('');
  const [showLink, setShowLink] = useState(false);
  const [userQuery, setUserQuery] = useState('');
  const [userResults, setUserResults] = useState<{ id: number; name: string; email: string; role: string }[]>([]);
  const [selectedUsers, setSelectedUsers] = useState<{ id: number; name: string; email: string; role: string }[]>([]);

  useEffect(() => {
    if (!show) {
      setUserQuery('');
      setUserResults([]);
      setSelectedUsers([]);
      return;
    }

    const query = userQuery.trim();

    if (query.length < 2) {
      setUserResults([]);
      return;
    }

    const timer = window.setTimeout(async () => {
      setIsSearchingUsers(true);
      try {
        const response = await axios.get('/api/users/search', {
          params: { q: query },
        });

        const results = Array.isArray(response.data) ? response.data : [];
        setUserResults(results);
      } catch (error) {
        console.error('Failed to search users:', error);
        setUserResults([]);
      } finally {
        setIsSearchingUsers(false);
      }
    }, 300);

    return () => window.clearTimeout(timer);
  }, [show, userQuery]);

  const handleAddRecipient = (user: { id: number; name: string; email: string; role: string }) => {
    setSelectedUsers((current) => {
      if (current.some((selected) => selected.id === user.id)) {
        return current;
      }

      return [...current, user];
    });

    setUserQuery('');
    setUserResults([]);
  };

  const handleRemoveRecipient = (userId: number) => {
    setSelectedUsers((current) => current.filter((user) => user.id !== userId));
  };

  const handleShare = async () => {
    setIsLoading(true);
    
    try {
      // Create share link
      const response = await axios.post('/api/collaboration/shares', {
        shared_id: documentId,
        slug: slug,
        name: documentName,
        valid_until: expirationDate || null,
        visibility: isPublic ? 'public' : 'private',
        recipient_emails: selectedUsers.map((user) => user.email),
      });

      const share = response.data.share;
      const link = `${window.location.origin}/shares/${slug}/${documentId}/${share.token}`;
      setShareLink(link);
      setShowLink(true);

      if (onSuccess) {
        onSuccess();
      }
    } catch (error) {
      console.error('Failed to share document:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleCopyLink = async () => {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(shareLink);
        alert('Link copied to clipboard!');
      } else {
        // Fallback for non-HTTPS contexts or old browsers
        const textArea = document.createElement('textarea');
        textArea.value = shareLink;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Link copied to clipboard!');
      }
    } catch (error) {
      console.error('Failed to copy link:', error);
      alert('Failed to copy link. Please copy it manually.');
    }
  };


  const handleClose = () => {
    setShowLink(false);
    setShareLink('');
    setExpirationDate('');
    setIsPublic(false);
    setUserQuery('');
    setUserResults([]);
    setSelectedUsers([]);
    onClose();
  };

    return (
        <Modal show={show} onClose={handleClose} title={`Share ${slug === 'stego' ? 'Stego File' : slug === 'folder' ? 'Folder' : 'Document'}`}>
      <div className="space-y-6">
        {!showLink ? (
          <>
            <div>
              <h3 className="text-lg font-medium text-gray-900">
                {documentName}
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                Create a download-only share link for this {slug === 'folder' ? 'folder' : slug === 'stego' ? 'stego file' : 'document'}.
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Search users to notify (optional)
              </label>

              <div className="relative">
                <input
                  type="text"
                  value={userQuery}
                  onChange={(e) => setUserQuery(e.target.value)}
                  placeholder="Search by name or email"
                  className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />

                {isSearchingUsers && (
                  <p className="mt-2 text-xs text-gray-500">Searching users...</p>
                )}

                {!isSearchingUsers && userQuery.trim().length >= 2 && userResults.length > 0 && (
                  <div className="mt-2 max-h-48 overflow-auto rounded-md border border-gray-200 bg-white shadow-sm">
                    {userResults.map((user) => (
                      <button
                        key={user.id}
                        type="button"
                        onClick={() => handleAddRecipient(user)}
                        disabled={selectedUsers.some((selected) => selected.id === user.id)}
                        className="flex w-full items-center justify-between gap-3 border-b border-gray-100 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
                      >
                        <span>
                          <span className="font-medium text-gray-900">{user.name}</span>{' '}
                          <span className="text-gray-500">({user.email})</span>
                        </span>
                        <span className="text-xs text-gray-400">{user.role}</span>
                      </button>
                    ))}
                  </div>
                )}

                {!isSearchingUsers && userQuery.trim().length >= 2 && userResults.length === 0 && (
                  <p className="mt-2 text-xs text-gray-500">No matching users found.</p>
                )}
              </div>
            </div>

            {selectedUsers.length > 0 && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Selected recipients
                </label>
                <div className="space-y-2">
                  {selectedUsers.map((user) => (
                    <div
                      key={user.id}
                      className="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
                    >
                      <div>
                        <div className="text-sm font-medium text-gray-900">{user.name}</div>
                        <div className="text-xs text-gray-500">{user.email}</div>
                      </div>
                      <button
                        type="button"
                        onClick={() => handleRemoveRecipient(user.id)}
                        className="text-sm font-medium text-red-600 hover:text-red-800"
                      >
                        Remove
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Expiration Date (Optional)
              </label>
              <input
                type="date"
                value={expirationDate}
                onChange={(e) => setExpirationDate(e.target.value)}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                min={new Date().toISOString().split('T')[0]}
              />
            </div>

            <div className="flex items-center">
              <input
                id="public"
                name="public"
                type="checkbox"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
              <label htmlFor="public" className="ml-2 block text-sm text-gray-700">
                Make this share link public
              </label>
            </div>

            <div className="flex space-x-3">
              <PrimaryButton
                onClick={handleShare}
                disabled={isLoading}
                className="flex-1"
              >
                {isLoading ? 'Sharing...' : 'Copy Link'}
              </PrimaryButton>
              <SecondaryButton 
                onClick={async () => {
                  if (selectedUsers.length === 0) {
                    alert('Please select at least one user to invite first');
                    return;
                  }
                  
                  setIsLoading(true);
                  try {
                    // Use existing share endpoint which sends email notifications
                    const response = await axios.post('/api/collaboration/shares', {
                      shared_id: documentId,
                      slug: slug,
                      name: documentName,
                      valid_until: expirationDate || null,
                      visibility: 'private',
                      recipient_emails: selectedUsers.map((user) => user.email),
                    });
                    
                    alert(`Success! ${selectedUsers.length} user${selectedUsers.length > 1 ? 's have' : ' has'} been notified. They will receive an email with a download link for this ${slug === 'stego' ? 'stego document' : 'document'}.`);
                    setSelectedUsers([]);
                    setUserQuery('');
                    
                    if (onSuccess) {
                      onSuccess();
                    }
                  } catch (error) {
                    console.error('Failed to send invitations:', error);
                    alert('Failed to send invitations. Please try again.');
                  } finally {
                    setIsLoading(false);
                  }
                }}
                disabled={isLoading || selectedUsers.length === 0}
                className="flex-1"
              >
                {isLoading ? 'Sending...' : 'Invite Users'}
              </SecondaryButton>
              <SecondaryButton onClick={handleClose}>
                Cancel
              </SecondaryButton>
            </div>
          </>
        ) : (
          <>
            <div className="text-center">
              <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg
                  className="w-6 h-6 text-green-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {slug === 'stego' ? 'Stego File' : 'Document'} Shared Successfully!
              </h3>
              <p className="text-sm text-gray-500 mb-4">
                Share this link with people you want to collaborate with.
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-3">
              <div className="flex items-center space-x-2">
                <input
                  type="text"
                  value={shareLink}
                  readOnly
                  className="flex-1 bg-transparent border-none text-sm text-gray-700"
                />
                <button
                  onClick={handleCopyLink}
                  className="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                >
                  Copy
                </button>
              </div>
            </div>

            <div className="flex space-x-3">
              <PrimaryButton onClick={handleClose} className="flex-1">
                Done
              </PrimaryButton>
            </div>
          </>
        )}
      </div>
      </Modal>
    );
  }
