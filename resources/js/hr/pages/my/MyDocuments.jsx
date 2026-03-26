import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    FileText,
    Upload,
    Download,
    Plus,
    X,
    Loader2,
    AlertCircle,
    File,
} from 'lucide-react';
import { fetchMyDocuments, uploadMyDocument } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../../components/ui/select';

const documentTypes = [
    { value: 'ic_copy', label: 'IC Copy' },
    { value: 'bank_statement', label: 'Bank Statement' },
    { value: 'resume', label: 'Resume' },
    { value: 'certificate', label: 'Certificate' },
    { value: 'other', label: 'Other' },
];

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatFileSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getDocumentTypeLabel(type) {
    const found = documentTypes.find((t) => t.value === type);
    return found ? found.label : type || '-';
}

export default function MyDocuments() {
    const queryClient = useQueryClient();
    const fileInputRef = useRef(null);
    const [showUploadForm, setShowUploadForm] = useState(false);
    const [uploadData, setUploadData] = useState({
        title: '',
        document_type: '',
        file: null,
    });
    const [uploadError, setUploadError] = useState(null);

    const { data: documentsData, isLoading, isError, error } = useQuery({
        queryKey: ['my-documents'],
        queryFn: fetchMyDocuments,
    });
    const documents = documentsData?.data ?? [];

    const uploadMutation = useMutation({
        mutationFn: (formData) => uploadMyDocument(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-documents'] });
            setShowUploadForm(false);
            setUploadData({ title: '', document_type: '', file: null });
            setUploadError(null);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        },
        onError: (err) => {
            setUploadError(err?.response?.data?.message || 'Failed to upload document');
        },
    });

    function handleUpload() {
        if (!uploadData.title || !uploadData.document_type || !uploadData.file) {
            setUploadError('Please fill in all fields and select a file');
            return;
        }

        const formData = new FormData();
        formData.append('title', uploadData.title);
        formData.append('document_type', uploadData.document_type);
        formData.append('file', uploadData.file);

        uploadMutation.mutate(formData);
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <AlertCircle className="h-10 w-10 text-red-400 mb-3" />
                <p className="text-sm text-zinc-600">
                    {error?.response?.data?.message || 'Failed to load documents'}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with Upload Button */}
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-zinc-700">
                    {documents.length} document{documents.length !== 1 ? 's' : ''}
                </h3>
                {!showUploadForm && (
                    <Button
                        size="sm"
                        onClick={() => {
                            setShowUploadForm(true);
                            setUploadError(null);
                        }}
                    >
                        <Upload className="h-3.5 w-3.5" />
                        Upload Document
                    </Button>
                )}
            </div>

            {/* Upload Form */}
            {showUploadForm && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Upload New Document</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div>
                            <Label htmlFor="doc-title">Title</Label>
                            <Input
                                id="doc-title"
                                value={uploadData.title}
                                onChange={(e) =>
                                    setUploadData((prev) => ({ ...prev, title: e.target.value }))
                                }
                                placeholder="Document title"
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label htmlFor="doc-type">Document Type</Label>
                            <Select
                                value={uploadData.document_type}
                                onValueChange={(value) =>
                                    setUploadData((prev) => ({ ...prev, document_type: value }))
                                }
                            >
                                <SelectTrigger className="mt-1">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {documentTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="doc-file">File</Label>
                            <Input
                                id="doc-file"
                                ref={fileInputRef}
                                type="file"
                                onChange={(e) =>
                                    setUploadData((prev) => ({
                                        ...prev,
                                        file: e.target.files?.[0] || null,
                                    }))
                                }
                                className="mt-1"
                            />
                        </div>
                        {uploadError && (
                            <p className="text-xs text-red-600">{uploadError}</p>
                        )}
                        <div className="flex gap-2 pt-1">
                            <Button
                                size="sm"
                                onClick={handleUpload}
                                disabled={uploadMutation.isPending}
                            >
                                {uploadMutation.isPending ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                ) : (
                                    <Upload className="h-3.5 w-3.5" />
                                )}
                                Upload
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    setShowUploadForm(false);
                                    setUploadData({ title: '', document_type: '', file: null });
                                    setUploadError(null);
                                }}
                            >
                                <X className="h-3.5 w-3.5" />
                                Cancel
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Document List */}
            {documents.length === 0 && !showUploadForm ? (
                <Card>
                    <CardContent className="py-10 text-center">
                        <FileText className="h-10 w-10 text-zinc-300 mx-auto mb-3" />
                        <p className="text-sm text-zinc-500">No documents uploaded yet</p>
                        <p className="text-xs text-zinc-400 mt-1">
                            Upload your personal documents for record keeping
                        </p>
                    </CardContent>
                </Card>
            ) : (
                documents.map((doc) => (
                    <Card key={doc.id}>
                        <CardContent className="pt-4 pb-4">
                            <div className="flex items-start justify-between">
                                <div className="flex items-start gap-3 min-w-0 flex-1">
                                    <div className="rounded-lg bg-zinc-100 p-2 shrink-0">
                                        <File className="h-4 w-4 text-zinc-600" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-zinc-900 truncate">
                                            {doc.title}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <Badge variant="secondary" className="text-[10px]">
                                                {getDocumentTypeLabel(doc.document_type)}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center gap-3 mt-1.5 text-xs text-zinc-500">
                                            <span>{formatDate(doc.created_at)}</span>
                                            {doc.file_size && (
                                                <span>{formatFileSize(doc.file_size)}</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                {doc.download_url && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8 shrink-0 ml-2"
                                        asChild
                                    >
                                        <a
                                            href={doc.download_url}
                                            download
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Download className="h-3.5 w-3.5" />
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))
            )}
        </div>
    );
}
