<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * База знаний: обучение персонала и регламентные документы.
 * Просмотр — все авторизованные; редактирование — manager и super-admin.
 */
class KnowledgeBaseController extends Controller
{
    /** Список категорий (главная БЗ). */
    public function index()
    {
        $categories = KbCategory::where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount(['articles' => fn ($q) => $q->where('is_published', true)])
            ->get();

        return view('knowledge-base.index', compact('categories'));
    }

    /** Статьи в категории. */
    public function category(string $slug)
    {
        $category = KbCategory::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $articles = $category->publishedArticles()->get();

        return view('knowledge-base.category', compact('category', 'articles'));
    }

    /** Просмотр статьи. */
    public function show(string $categorySlug, string $articleSlug)
    {
        $category = KbCategory::where('slug', $categorySlug)->where('is_published', true)->firstOrFail();
        $article = KbArticle::where('category_id', $category->id)
            ->where('slug', $articleSlug)
            ->where('is_published', true)
            ->with('author')
            ->firstOrFail();

        return view('knowledge-base.show', compact('category', 'article'));
    }

    /** Управление категориями (только manager, super-admin). */
    public function categoriesIndex()
    {
        $this->authorizeKbManage();
        $categories = KbCategory::orderBy('sort_order')->orderBy('name')->withCount('articles')->get();

        return view('knowledge-base.admin.categories-index', compact('categories'));
    }

    public function categoryCreate()
    {
        $this->authorizeKbManage();
        return view('knowledge-base.admin.category-form', ['category' => null]);
    }

    public function categoryStore(Request $request)
    {
        $this->authorizeKbManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:kb_categories,slug',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        KbCategory::create($data);

        return redirect()->route('kb.categories.index')->with('success', 'Категория создана.');
    }

    public function categoryEdit(KbCategory $kbCategory)
    {
        $this->authorizeKbManage();
        return view('knowledge-base.admin.category-form', ['category' => $kbCategory]);
    }

    public function categoryUpdate(Request $request, KbCategory $kbCategory)
    {
        $this->authorizeKbManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:kb_categories,slug,' . $kbCategory->id,
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $kbCategory->update($data);

        return redirect()->route('kb.categories.index')->with('success', 'Категория обновлена.');
    }

    public function categoryDestroy(KbCategory $kbCategory)
    {
        $this->authorizeKbManage();
        $kbCategory->delete();

        return redirect()->route('kb.categories.index')->with('success', 'Категория удалена.');
    }

    /** Управление статьями. */
    public function articlesIndex(Request $request)
    {
        $this->authorizeKbManage();
        $query = KbArticle::with('category', 'author');
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $articles = $query->orderBy('sort_order')->orderBy('title')->paginate(20)->withQueryString();
        $categories = KbCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('knowledge-base.admin.articles-index', compact('articles', 'categories'));
    }

    public function articleCreate()
    {
        $this->authorizeKbManage();
        $categories = KbCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('knowledge-base.admin.article-form', ['article' => null, 'categories' => $categories]);
    }

    public function articleStore(Request $request)
    {
        $this->authorizeKbManage();
        $data = $request->validate([
            'category_id' => 'required|exists:kb_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'video_urls_text' => 'nullable|string|max:5000',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['created_by'] = auth()->id();
        $data['video_urls'] = $this->normalizeVideoUrlsFromText($request->input('video_urls_text') ?? '');
        $data['images'] = [];
        $data['is_published'] = $request->has('is_published') ? $request->boolean('is_published') : true;
        $data['images'] = json_encode($data['images']);
        // video_urls передаём массивом — cast 'array' в модели сам кодирует в JSON

        $article = KbArticle::create($data);

        $uploadedFiles = $this->collectUploadedImages($request);
        if (count($uploadedFiles) > 0) {
            $paths = $this->storeArticleImages($article, $uploadedFiles);
            if (! empty($paths)) {
                $article->update(['images' => json_encode($paths)]);
            }
        }

        return redirect()->route('kb.articles.index')->with('success', 'Статья создана.');
    }

    public function articleEdit(KbArticle $kbArticle)
    {
        $this->authorizeKbManage();
        $categories = KbCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('knowledge-base.admin.article-form', ['article' => $kbArticle, 'categories' => $categories]);
    }

    public function articleUpdate(Request $request, KbArticle $kbArticle)
    {
        $this->authorizeKbManage();
        $data = $request->validate([
            'category_id' => 'required|exists:kb_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'video_urls_text' => 'nullable|string|max:5000',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['video_urls'] = $this->normalizeVideoUrlsFromText($request->input('video_urls_text') ?? '');
        $data['is_published'] = $request->has('is_published') ? $request->boolean('is_published') : (bool) $kbArticle->is_published;

        $currentImages = is_array($kbArticle->images) ? $kbArticle->images : [];
        $removeImages = $request->input('remove_images', []);
        $removeImages = is_array($removeImages) ? $removeImages : ($removeImages ? [$removeImages] : []);
        $images = array_values(array_diff($currentImages, $removeImages));

        $uploadedFiles = $this->collectUploadedImages($request);
        if (count($uploadedFiles) > 0) {
            $newPaths = $this->storeArticleImages($kbArticle, $uploadedFiles);
            $images = array_merge($images, $newPaths);
        }

        $this->deleteRemovedArticleImages($kbArticle, $currentImages, $images);
        $data['images'] = json_encode($images);
        // video_urls — массив, cast 'array' в модели сам кодирует в JSON (ручной json_encode приводил к двойному кодированию)

        $kbArticle->update($data);

        return redirect()->route('kb.articles.index')->with('success', 'Статья обновлена.');
    }

    /** Загрузить одно фото в статью (отдельный запрос, как загрузка планировки в карточке квартиры CRM). */
    public function articlePhotoStore(Request $request, KbArticle $kbArticle)
    {
        $this->authorizeKbManage();
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);
        $path = $request->file('photo')->store('kb_articles', 'public');
        if (! is_string($path) || $path === '') {
            return redirect()->route('kb.articles.edit', $kbArticle)->with('error', 'Не удалось сохранить файл.');
        }
        $images = is_array($kbArticle->images) ? $kbArticle->images : [];
        $images[] = $path;
        $kbArticle->images = $images;
        $kbArticle->save();

        return redirect()->route('kb.articles.edit', $kbArticle)->with('success', 'Фото добавлено.');
    }

    public function articleDestroy(KbArticle $kbArticle)
    {
        $this->authorizeKbManage();
        $this->deleteArticleImagesDirectory($kbArticle);
        $kbArticle->delete();

        return redirect()->route('kb.articles.index')->with('success', 'Статья удалена.');
    }

    /** Сохранить загруженные фото в storage/app/public/kb_articles/ (как в CRM — папка apartments). */
    private function storeArticleImages(KbArticle $article, array $files): array
    {
        $paths = [];
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('kb_articles', 'public');
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /** Собрать файлы из запроса в массив (input name="images[]" может вернуть один файл или массив). */
    private function collectUploadedImages(Request $request): array
    {
        $files = $request->file('images');
        if (is_array($files)) {
            return array_values($files);
        }
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            return [$files];
        }
        return [];
    }

    /** Удалить с диска фото, которые больше не привязаны к статье. */
    private function deleteRemovedArticleImages(KbArticle $article, array $current, array $kept): void
    {
        $toRemove = array_diff($current, $kept);
        foreach ($toRemove as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /** Удалить с диска все фото статьи. */
    private function deleteArticleImagesDirectory(KbArticle $article): void
    {
        $images = is_array($article->images) ? $article->images : [];
        foreach ($images as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** Из текста (одна ссылка на строку) — массив URL. Принимаем любые строки, начинающиеся с http(s)://. */
    private function normalizeVideoUrlsFromText(?string $text): array
    {
        $text = $text ?? '';
        $lines = preg_split('/\r?\n/', $text);
        $urls = array_map('trim', $lines);
        $urls = array_filter($urls);
        $result = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (strlen($url) <= 500 && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                $result[] = $url;
            } elseif (filter_var($url, FILTER_VALIDATE_URL) && strlen($url) <= 500) {
                $result[] = $url;
            }
        }
        return $result;
    }

    private function authorizeKbManage(): void
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->hasFullStoreAccess()) {
            abort(403, 'Недостаточно прав для управления базой знаний.');
        }
    }
}
