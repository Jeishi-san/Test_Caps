import { useEffect, useState } from 'react';
import axios from 'axios';

export interface TokenInfo {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface UseApiTokensOptions {
    initialTokens?: TokenInfo[];
    fetchOnMount?: boolean;
    revokeConfirmText?: string;
}

interface UseApiTokensResult {
    tokens: TokenInfo[];
    setTokens: React.Dispatch<React.SetStateAction<TokenInfo[]>>;
    creating: boolean;
    setCreating: React.Dispatch<React.SetStateAction<boolean>>;
    name: string;
    setName: React.Dispatch<React.SetStateAction<string>>;
    newToken: string | null;
    setNewToken: React.Dispatch<React.SetStateAction<string | null>>;
    loading: boolean;
    copied: boolean;
    error: string;
    setError: React.Dispatch<React.SetStateAction<string>>;
    createToken: () => Promise<void>;
    revokeToken: (id: number) => Promise<void>;
    copyToken: () => void;
    refreshTokens: () => Promise<void>;
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error) && typeof error.response?.data?.message === 'string') {
        return error.response.data.message;
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    return fallback;
}

export function useApiTokens(options: UseApiTokensOptions = {}): UseApiTokensResult {
    const {
        initialTokens = [],
        fetchOnMount = false,
        revokeConfirmText = 'Revoke this token?',
    } = options;

    const [tokens, setTokens] = useState<TokenInfo[]>(initialTokens);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [newToken, setNewToken] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setTokens(initialTokens);
    }, [initialTokens]);

    const refreshTokens = async () => {
        try {
            const response = await axios.get('/api/auth/tokens');
            setTokens(response.data ?? []);
        } catch (err: unknown) {
            setError(getErrorMessage(err, 'Failed to load tokens.'));
        }
    };

    useEffect(() => {
        if (fetchOnMount) {
            void refreshTokens();
        }
    }, [fetchOnMount]);

    const createToken = async () => {
        setError('');

        if (!name.trim()) {
            setError('Token name is required.');
            return;
        }

        setLoading(true);
        try {
            const response = await axios.post('/api/auth/tokens', { name: name.trim() });
            setNewToken(response.data.token);

            const fallbackToken: TokenInfo = {
                id: Date.now(),
                name: name.trim(),
                last_used_at: null,
                expires_at: null,
                created_at: new Date().toISOString(),
            };

            setTokens((prev) => [response.data.token_info ?? fallbackToken, ...prev]);
            setName('');
            setCreating(false);
        } catch (err: unknown) {
            setError(getErrorMessage(err, 'Failed to create token.'));
        } finally {
            setLoading(false);
        }
    };

    const revokeToken = async (id: number) => {
        if (!window.confirm(revokeConfirmText)) {
            return;
        }

        try {
            await axios.delete(`/api/auth/tokens/${id}`);
            setTokens((prev) => prev.filter((token) => token.id !== id));
        } catch (err: unknown) {
            setError(getErrorMessage(err, 'Failed to revoke token.'));
        }
    };

    const copyToken = () => {
        if (!newToken) {
            return;
        }

        void navigator.clipboard.writeText(newToken).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    return {
        tokens,
        setTokens,
        creating,
        setCreating,
        name,
        setName,
        newToken,
        setNewToken,
        loading,
        copied,
        error,
        setError,
        createToken,
        revokeToken,
        copyToken,
        refreshTokens,
    };
}
