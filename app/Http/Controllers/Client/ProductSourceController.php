<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductSourceRequest;
use App\Http\Requests\UpdateProductSourceRequest;
use App\Jobs\SyncProductSourceJob;
use App\Models\ProductCache;
use App\Models\ProductSource;
use App\Services\ProductSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductSourceController extends Controller
{
    public function __construct(private readonly ProductSourceService $sourceService) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->effectiveTenantId();

        $sources = ProductSource::where('user_id', $userId)
            ->withCount('products')
            ->latest()
            ->get();

        return Inertia::render('client/product-sources', [
            'sources' => $sources,
        ]);
    }

    public function store(StoreProductSourceRequest $request): RedirectResponse
    {
        $userId = $request->user()->effectiveTenantId();

        ProductSource::create(array_merge(
            $request->validated(),
            ['user_id' => $userId]
        ));

        return back()->with('success', 'Product source created.');
    }

    public function update(UpdateProductSourceRequest $request, ProductSource $productSource): RedirectResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        $productSource->update($request->validated());

        return back()->with('success', 'Product source updated.');
    }

    public function destroy(Request $request, ProductSource $productSource): RedirectResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        $productSource->delete();

        return back()->with('success', 'Product source deleted.');
    }

    public function sync(Request $request, ProductSource $productSource): RedirectResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        if ($productSource->source_type === 'manual') {
            return back()->with('error', 'Manual sources do not need syncing.');
        }

        SyncProductSourceJob::dispatch($productSource);

        return back()->with('success', 'Sync queued. Products will update shortly.');
    }

    public function testConnection(Request $request, ProductSource $productSource): JsonResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        $result = $this->sourceService->testConnection($productSource);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function products(Request $request, ProductSource $productSource): Response
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        $products = ProductCache::where('source_id', $productSource->id)
            ->orderBy('product_name')
            ->paginate(30);

        return Inertia::render('client/product-sources', [
            'sources' => ProductSource::where('user_id', $productSource->user_id)->withCount('products')->latest()->get(),
            'viewSource' => $productSource,
            'products' => $products,
        ]);
    }

    public function storeManualProduct(Request $request, ProductSource $productSource): RedirectResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        if ($productSource->source_type !== 'manual') {
            return back()->with('error', 'Only manual sources support direct product upload.');
        }

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stock_status' => ['nullable', 'in:instock,outofstock'],
            'category' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100'],
            'keywords' => ['nullable', 'string'],
            'image_urls' => ['required', 'string'],
        ]);

        $imageUrls = collect(explode("\n", $validated['image_urls']))
            ->map(fn ($url) => trim($url))
            ->filter(fn ($url) => str_starts_with($url, 'https://'))
            ->values()
            ->all();

        if (empty($imageUrls)) {
            return back()->with('error', 'At least one valid HTTPS image URL is required.');
        }

        $keywords = ! empty($validated['keywords'] ?? null)
            ? collect(explode(',', $validated['keywords']))->map(fn ($k) => trim($k))->filter()->values()->all()
            : [];

        ProductCache::create([
            'user_id' => $productSource->user_id,
            'source_id' => $productSource->id,
            'product_name' => $validated['product_name'],
            'price' => $validated['price'] ?? null,
            'product_url' => $validated['product_url'] ?? null,
            'description' => $validated['description'] ?? null,
            'stock_status' => $validated['stock_status'] ?? 'instock',
            'category' => $validated['category'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'keywords' => $keywords,
            'image_urls' => $imageUrls,
            'last_synced_at' => now(),
        ]);

        return back()->with('success', 'Product added.');
    }

    public function destroyProduct(Request $request, ProductSource $productSource, ProductCache $product): RedirectResponse
    {
        abort_if($productSource->user_id !== $request->user()->effectiveTenantId(), 403);

        abort_if($product->source_id !== $productSource->id, 403);

        $product->delete();

        return back()->with('success', 'Product deleted.');
    }
}
