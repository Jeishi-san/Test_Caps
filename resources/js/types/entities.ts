export type Visibility = 'public' | 'private';

export interface TagEntity {
    id: number;
    name: string;
}

export interface CategoryEntity {
    id: number;
    name: string;
}

export interface FolderEntity {
    id: number;
    name: string;
    parent_id: number | null;
    visibility?: Visibility | string;
    position?: number;
    updated_at?: string;
    categories?: CategoryEntity[];
    subfolders?: FolderEntity[];
    children?: FolderEntity[];
}

export interface OwnerSummary {
    id: number;
    name: string;
    email: string;
}

export interface ShareRecipientUser extends OwnerSummary {
    role?: string;
}

export interface DocumentEntity {
    id: number;
    name: string;
    file_path: string;
    extension: string;
    size: number;
    visibility?: Visibility | string;
    folder_id: number | null;
    created_at?: string;
    updated_at?: string;
    is_stegoed?: boolean;
    is_starred?: boolean;
    tags?: TagEntity[];
    ingest_status?: string;
    ingest_error?: string;
}
