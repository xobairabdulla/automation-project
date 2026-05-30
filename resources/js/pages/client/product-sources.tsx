import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, ExternalLink, Package, Plus, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ProductSource {
    id: number;
    name: string;
    source_type: 'woocommerce_api' | 'shopify_api' | 'custom_api' | 'manual';
    website_url: string | null;
    api_url: string | null;
    is_active: boolean;
    product_image_reply_enabled: boolean;
    max_images_per_reply: number;
    last_synced_at: string | null;
    products_count: number;
}

interface Product {
    id: number;
    product_name: string;
    price: string | null;
    category: string | null;
    stock_status: string | null;
    image_urls: string[];
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
}

interface Props {
    sources: ProductSource[];
    viewSource?: ProductSource;
    products?: Paginator<Product>;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Product Sources', href: '/product-sources' },
];

const SOURCE_LABELS: Record<string, string> = {
    woocommerce_api: 'WooCommerce',
    shopify_api: 'Shopify',
    custom_api: 'Custom API',
    manual: 'Manual',
};

export default function ProductSourcesPage({ sources, viewSource, products }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [editSource, setEditSource] = useState<ProductSource | null>(null);
    const [addProductOpen, setAddProductOpen] = useState(false);
    const [testResult, setTestResult] = useState<{ ok: boolean; message: string; product_count?: number } | null>(null);
    const [testing, setTesting] = useState(false);
    const [syncing, setSyncing] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        source_type: 'woocommerce_api',
        website_url: '',
        api_url: '',
        api_key: '',
        api_secret: '',
        is_active: true,
        product_image_reply_enabled: true,
        max_images_per_reply: 4,
    });

    const editForm = useForm({
        name: '',
        source_type: 'woocommerce_api' as string,
        website_url: '',
        api_url: '',
        api_key: '',
        api_secret: '',
        is_active: true,
        product_image_reply_enabled: true,
        max_images_per_reply: 4,
    });

    const productForm = useForm({
        product_name: '',
        price: '',
        product_url: '',
        description: '',
        stock_status: 'instock',
        category: '',
        sku: '',
        keywords: '',
        image_urls: '',
    });

    function openCreate() {
        createForm.reset();
        setCreateOpen(true);
        setTestResult(null);
    }

    function openEdit(source: ProductSource) {
        editForm.setData({
            name: source.name,
            source_type: source.source_type,
            website_url: source.website_url ?? '',
            api_url: source.api_url ?? '',
            api_key: '',
            api_secret: '',
            is_active: source.is_active,
            product_image_reply_enabled: source.product_image_reply_enabled,
            max_images_per_reply: source.max_images_per_reply,
        });
        setEditSource(source);
        setTestResult(null);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/product-sources', { onSuccess: () => setCreateOpen(false) });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editSource) return;
        editForm.put(`/product-sources/${editSource.id}`, { onSuccess: () => setEditSource(null) });
    }

    function deleteSource(source: ProductSource) {
        if (!confirm(`Delete "${source.name}" and all its cached products?`)) return;
        router.delete(`/product-sources/${source.id}`);
    }

    function syncSource(source: ProductSource) {
        setSyncing(source.id);
        router.post(
            `/product-sources/${source.id}/sync`,
            {},
            { onFinish: () => setSyncing(null) },
        );
    }

    async function testConnection(sourceId: number) {
        setTesting(true);
        setTestResult(null);
        try {
            const res = await fetch(`/product-sources/${sourceId}/test`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            setTestResult(data);
        } catch {
            setTestResult({ ok: false, message: 'Network error.' });
        } finally {
            setTesting(false);
        }
    }

    function submitProduct(e: React.FormEvent) {
        e.preventDefault();
        if (!viewSource) return;
        productForm.post(`/product-sources/${viewSource.id}/products`, {
            onSuccess: () => {
                setAddProductOpen(false);
                productForm.reset();
            },
        });
    }

    function deleteProduct(productId: number) {
        if (!viewSource) return;
        if (!confirm('Delete this product?')) return;
        router.delete(`/product-sources/${viewSource.id}/products/${productId}`);
    }

    const sourceForm = (data: typeof createForm.data, setData: typeof createForm.setData, processing: boolean, errors: typeof createForm.errors) => (
        <div className="grid gap-3">
            <div>
                <Label>Source Name</Label>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. My WooCommerce Store" />
                {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
            </div>
            <div>
                <Label>Source Type</Label>
                <Select value={data.source_type} onValueChange={(v) => setData('source_type', v as never)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        {Object.entries(SOURCE_LABELS).map(([k, v]) => (
                            <SelectItem key={k} value={k}>{v}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            {data.source_type !== 'manual' && (
                <>
                    <div>
                        <Label>Website URL</Label>
                        <Input value={data.website_url} onChange={(e) => setData('website_url', e.target.value)} placeholder="https://yourstore.com" />
                        {errors.website_url && <p className="text-xs text-red-500 mt-1">{errors.website_url}</p>}
                    </div>
                    {data.source_type !== 'shopify_api' && (
                        <div>
                            <Label>{data.source_type === 'woocommerce_api' ? 'WooCommerce Store URL' : 'API Endpoint URL'}</Label>
                            <Input value={data.api_url} onChange={(e) => setData('api_url', e.target.value)} placeholder="https://yourstore.com" />
                            {errors.api_url && <p className="text-xs text-red-500 mt-1">{errors.api_url}</p>}
                        </div>
                    )}
                    {data.source_type === 'shopify_api' && (
                        <div>
                            <Label>Shopify Store URL</Label>
                            <Input value={data.api_url} onChange={(e) => setData('api_url', e.target.value)} placeholder="https://your-store.myshopify.com" />
                        </div>
                    )}
                    <div>
                        <Label>
                            {data.source_type === 'woocommerce_api' ? 'Consumer Key' : data.source_type === 'shopify_api' ? 'Access Token' : 'API Key (Bearer)'}
                        </Label>
                        <Input type="password" value={data.api_key} onChange={(e) => setData('api_key', e.target.value)} placeholder="Leave blank to keep existing" />
                    </div>
                    {data.source_type === 'woocommerce_api' && (
                        <div>
                            <Label>Consumer Secret</Label>
                            <Input type="password" value={data.api_secret} onChange={(e) => setData('api_secret', e.target.value)} placeholder="Leave blank to keep existing" />
                        </div>
                    )}
                </>
            )}
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <Label>Max Images / Reply</Label>
                    <Input type="number" min={1} max={10} value={data.max_images_per_reply} onChange={(e) => setData('max_images_per_reply', Number(e.target.value) as never)} />
                </div>
                <div className="flex flex-col gap-1 pt-1">
                    <Label>Image Reply</Label>
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" checked={data.product_image_reply_enabled as boolean} onChange={(e) => setData('product_image_reply_enabled', e.target.checked as never)} />
                        Enabled
                    </label>
                </div>
            </div>
            <Button type="submit" disabled={processing}>Save</Button>
        </div>
    );

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Product Sources" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Product Image Sources</h1>
                        <p className="text-muted-foreground text-sm">
                            Connect your product catalog so the bot can send product images to buyers.
                        </p>
                    </div>
                    <Dialog open={createOpen} onOpenChange={(open) => { if (open) openCreate(); else setCreateOpen(false); }}>
                        <DialogTrigger asChild>
                            <Button><Plus size={16} className="mr-1" />Add Source</Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-lg">
                            <DialogHeader><DialogTitle>Add Product Source</DialogTitle></DialogHeader>
                            <form onSubmit={submitCreate}>
                                {sourceForm(createForm.data, createForm.setData, createForm.processing, createForm.errors)}
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {flash?.success && <Alert><AlertDescription>{flash.success}</AlertDescription></Alert>}
                {flash?.error && <Alert variant="destructive"><AlertDescription>{flash.error}</AlertDescription></Alert>}

                {sources.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            <Package size={32} className="mx-auto mb-3 opacity-30" />
                            No product sources yet. Add one to enable product image replies.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {sources.map((source) => (
                            <Card key={source.id} className={source.id === viewSource?.id ? 'ring-2 ring-violet-500' : ''}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base">{source.name}</CardTitle>
                                            <div className="flex flex-wrap gap-1 mt-1">
                                                <Badge variant="outline">{SOURCE_LABELS[source.source_type]}</Badge>
                                                <Badge variant={source.is_active ? 'default' : 'secondary'}>
                                                    {source.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                                {source.product_image_reply_enabled && (
                                                    <Badge variant="outline" className="text-green-600 border-green-300">Image Reply On</Badge>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex gap-1 shrink-0">
                                            <Button variant="outline" size="sm" onClick={() => openEdit(source)}>Edit</Button>
                                            <Button variant="destructive" size="sm" onClick={() => deleteSource(source)}>
                                                <Trash2 size={14} />
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-sm text-muted-foreground space-y-1 mb-3">
                                        <div className="flex items-center gap-2">
                                            <Package size={13} />
                                            <span>{source.products_count} products cached</span>
                                        </div>
                                        {source.last_synced_at && (
                                            <div className="text-xs">Last synced: {new Date(source.last_synced_at).toLocaleString()}</div>
                                        )}
                                        <div className="text-xs">Max {source.max_images_per_reply} images per reply</div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {source.source_type !== 'manual' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={syncing === source.id}
                                                    onClick={() => syncSource(source)}
                                                >
                                                    <RefreshCw size={13} className={`mr-1 ${syncing === source.id ? 'animate-spin' : ''}`} />
                                                    Sync
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={testing}
                                                    onClick={() => testConnection(source.id)}
                                                >
                                                    Test Connection
                                                </Button>
                                            </>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.get(`/product-sources/${source.id}/products`)}
                                        >
                                            <ExternalLink size={13} className="mr-1" />
                                            View Products
                                        </Button>
                                    </div>
                                    {testResult && (
                                        <div className={`flex items-center gap-2 mt-2 text-sm ${testResult.ok ? 'text-green-600' : 'text-red-600'}`}>
                                            {testResult.ok ? <CheckCircle size={14} /> : <XCircle size={14} />}
                                            {testResult.message}
                                            {testResult.product_count !== undefined && ` (${testResult.product_count} products)`}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Product list for selected source */}
                {viewSource && products && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">Products — {viewSource.name}</CardTitle>
                                {viewSource.source_type === 'manual' && (
                                    <Dialog open={addProductOpen} onOpenChange={setAddProductOpen}>
                                        <DialogTrigger asChild>
                                            <Button size="sm"><Plus size={14} className="mr-1" />Add Product</Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-w-lg">
                                            <DialogHeader><DialogTitle>Add Manual Product</DialogTitle></DialogHeader>
                                            <form onSubmit={submitProduct} className="grid gap-3">
                                                <div>
                                                    <Label>Product Name *</Label>
                                                    <Input value={productForm.data.product_name} onChange={(e) => productForm.setData('product_name', e.target.value)} required />
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <Label>Price</Label>
                                                        <Input type="number" step="0.01" value={productForm.data.price} onChange={(e) => productForm.setData('price', e.target.value)} />
                                                    </div>
                                                    <div>
                                                        <Label>Category</Label>
                                                        <Input value={productForm.data.category} onChange={(e) => productForm.setData('category', e.target.value)} />
                                                    </div>
                                                </div>
                                                <div>
                                                    <Label>Product URL</Label>
                                                    <Input value={productForm.data.product_url} onChange={(e) => productForm.setData('product_url', e.target.value)} placeholder="https://..." />
                                                </div>
                                                <div>
                                                    <Label>Description</Label>
                                                    <textarea className="w-full rounded border p-2 text-sm" rows={2} value={productForm.data.description} onChange={(e) => productForm.setData('description', e.target.value)} />
                                                </div>
                                                <div>
                                                    <Label>Keywords (comma-separated)</Label>
                                                    <Input value={productForm.data.keywords} onChange={(e) => productForm.setData('keywords', e.target.value)} placeholder="black panjabi, kurta, cotton" />
                                                </div>
                                                <div>
                                                    <Label>Image URLs (one per line, must start with https://) *</Label>
                                                    <textarea
                                                        className="w-full rounded border p-2 text-sm font-mono"
                                                        rows={4}
                                                        required
                                                        value={productForm.data.image_urls}
                                                        onChange={(e) => productForm.setData('image_urls', e.target.value)}
                                                        placeholder="https://yoursite.com/image1.jpg&#10;https://yoursite.com/image2.jpg"
                                                    />
                                                    {productForm.errors.image_urls && <p className="text-xs text-red-500">{productForm.errors.image_urls}</p>}
                                                </div>
                                                <Button type="submit" disabled={productForm.processing}>Add Product</Button>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {products.data.length === 0 ? (
                                <p className="text-sm text-muted-foreground py-4 text-center">
                                    No products yet.{' '}
                                    {viewSource.source_type !== 'manual'
                                        ? 'Click "Sync" to fetch products from your store.'
                                        : 'Add products manually above.'}
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {products.data.map((product) => (
                                        <div key={product.id} className="flex items-start justify-between gap-3 rounded border p-3">
                                            <div className="flex gap-3 items-start flex-1 min-w-0">
                                                {product.image_urls?.[0] && (
                                                    <img
                                                        src={product.image_urls[0]}
                                                        alt={product.product_name}
                                                        className="w-14 h-14 rounded object-cover shrink-0 border"
                                                    />
                                                )}
                                                <div className="min-w-0">
                                                    <p className="font-medium text-sm truncate">{product.product_name}</p>
                                                    <div className="flex flex-wrap gap-1 mt-0.5">
                                                        {product.category && <Badge variant="outline" className="text-xs">{product.category}</Badge>}
                                                        {product.price && <Badge variant="outline" className="text-xs">৳{product.price}</Badge>}
                                                        {product.stock_status && (
                                                            <Badge variant={product.stock_status === 'instock' ? 'default' : 'secondary'} className="text-xs">
                                                                {product.stock_status}
                                                            </Badge>
                                                        )}
                                                        <Badge variant="outline" className="text-xs">{product.image_urls?.length ?? 0} img</Badge>
                                                    </div>
                                                </div>
                                            </div>
                                            {viewSource.source_type === 'manual' && (
                                                <Button variant="destructive" size="sm" onClick={() => deleteProduct(product.id)}>
                                                    <Trash2 size={13} />
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                            {products.last_page > 1 && (
                                <div className="flex justify-center gap-2 mt-4">
                                    {Array.from({ length: products.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            size="sm"
                                            variant={page === products.current_page ? 'default' : 'outline'}
                                            onClick={() => router.get(`/product-sources/${viewSource.id}/products`, { page })}
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Edit Source Dialog */}
            <Dialog open={editSource !== null} onOpenChange={(open) => { if (!open) setEditSource(null); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader><DialogTitle>Edit {editSource?.name}</DialogTitle></DialogHeader>
                    <form onSubmit={submitEdit}>
                        {sourceForm(editForm.data, editForm.setData, editForm.processing, editForm.errors)}
                    </form>
                </DialogContent>
            </Dialog>
        </ClientDashboardLayout>
    );
}
