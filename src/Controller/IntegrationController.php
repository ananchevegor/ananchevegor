<?php

namespace App\Controller;

use InvalidArgumentException;
use App\Service\PDOService;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class IntegrationController extends AbstractController
{
    private string $sqlInsert = '
        INSERT INTO public.blog_posts (slug, title, excerpt, "content", post_type, status, cover_image_url, seo_title, seo_description, is_featured, published_at, created_at, updated_at)
        VALUES(:slug, :title, :excerpt, :content, :post_type, :status, :cover_image_url, :seo_title, :seo_description, :is_featured, :published_at, now(), now());
    ';
    private string $sqlUpdateById = '
        UPDATE public.blog_posts
        SET
            slug = :slug,
            title = :title,
            excerpt = :excerpt,
            "content" = :content,
            post_type = :post_type,
            status = :status,
            cover_image_url = :cover_image_url,
            seo_title = :seo_title,
            seo_description = :seo_description,
            is_featured = :is_featured,
            published_at = :published_at,
            updated_at = now()
        WHERE id = :id
    ';
    private string $sqlSelectAllPosts = "
        SELECT
            id,
            slug,
            title,
            excerpt,
            post_type,
            status,
            cover_image_url,
            seo_title,
            seo_description,
            is_featured,
            published_at,
            created_at,
            updated_at
        FROM public.blog_posts
        ORDER BY updated_at DESC NULLS LAST, created_at DESC NULLS LAST, id DESC
    ";
    private string $sqlSelectById = 'SELECT * FROM public.blog_posts WHERE id = :id LIMIT 1';
    private string $sqlDeleteById = 'DELETE FROM public.blog_posts WHERE id = :id';
    private array $allowedPostTypes = ['research', 'test', 'news'];
    private array $allowedStatuses = ['draft', 'published', 'archived'];
    private array $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'svg'];
    

    #[Route('/integrations/new-post', name: 'integrations-new-post', methods: ["POST"])]
    public function integrationResearchNewPost(Request $request, PDOService $pdoService): Response
    {
        $pdo = $pdoService->getConnection();

        $slug = trim((string) $request->request->get('slug', ''));
        $title = trim((string) $request->request->get('title', ''));
        $excerpt = $request->request->get('excerpt') ?: null;
        $content = (string) $request->request->get('content', '');
        $postType = (string) $request->request->get('post_type', 'research');
        $status = (string) $request->request->get('status', 'draft');
        $coverImage = $request->files->get('cover_image');
        $coverImageUrl = null;
        $seoTitle = $request->request->get('seo_title') ?: null;
        $seoDescription = $request->request->get('seo_description') ?: null;
        $isFeatured = $request->request->getBoolean('is_featured', false);
        $publishedAt = $request->request->get('published_at') ?: null;

        if ($slug === '' || $title === '' || trim($content) === '') {
            throw new InvalidArgumentException('Slug, title, and content are required');
        }

        if (!in_array($postType, $this->allowedPostTypes, true)) {
            throw new InvalidArgumentException('Invalid post_type');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        if ($coverImage instanceof UploadedFile) {
            if (!$coverImage->isValid()) {
                throw new InvalidArgumentException('Cover image upload failed');
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/assets/posts';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('Unable to create upload directory');
            }

            $extension = strtolower((string) $coverImage->getClientOriginalExtension());
            if ($extension === '') {
                $extension = strtolower((string) pathinfo($coverImage->getClientOriginalName(), PATHINFO_EXTENSION));
            }

            
            if ($extension === '' || !in_array($extension, $this->allowedImageExtensions, true)) {
                throw new InvalidArgumentException('Unsupported cover image format');
            }

            $safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '-', pathinfo($coverImage->getClientOriginalName(), PATHINFO_FILENAME));
            $safeBaseName = trim((string) $safeBaseName, '-');
            $safeBaseName = $safeBaseName !== '' ? strtolower($safeBaseName) : 'posts';
            $filename = sprintf('%s-%s.%s', $safeBaseName, bin2hex(random_bytes(6)), $extension);

            $coverImage->move($uploadDir, $filename);
            $coverImageUrl = '/assets/posts/' . $filename;
        }

        $stmt = $pdo->prepare($this->sqlInsert);
        $stmt->bindValue(':slug', trim($slug), PDO::PARAM_STR);
        $stmt->bindValue(':title', trim($title), PDO::PARAM_STR);
        $stmt->bindValue(':excerpt', $excerpt, $excerpt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->bindValue(':post_type', $postType, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':cover_image_url', $coverImageUrl, $coverImageUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':seo_title', $seoTitle, $seoTitle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':seo_description', $seoDescription, $seoDescription === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_BOOL);
        $stmt->bindValue(':published_at', $publishedAt, $publishedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return $this->render('integrations/article-success.html.twig', [], new Response('', Response::HTTP_CREATED));
    }

    #[Route('/integrations/update-post/{id}', name: 'integrations-update-post', requirements: ['id' => '\d+'], methods: ["POST", "PATCH"])]
    public function integrationResearchUpdatePost(Request $request, PDOService $pdoService, int $id): Response
    {
        $pdo = $pdoService->getConnection();
        $post = $this->findPostById($pdo, $id);

        $slug = trim((string) $request->request->get('slug', ''));
        $title = trim((string) $request->request->get('title', ''));
        $excerpt = $request->request->get('excerpt') ?: null;
        $content = (string) $request->request->get('content', '');
        $postType = (string) $request->request->get('post_type', 'research');
        $status = (string) $request->request->get('status', 'draft');
        $coverImage = $request->files->get('cover_image');
        $coverImageUrl = $request->request->get('current_cover_image_url') ?: null;
        $seoTitle = $request->request->get('seo_title') ?: null;
        $seoDescription = $request->request->get('seo_description') ?: null;
        $isFeatured = $request->request->getBoolean('is_featured', false);
        $publishedAt = $request->request->get('published_at') ?: null;

        if ($slug === '' || $title === '' || trim($content) === '') {
            throw new InvalidArgumentException('Slug, title, and content are required');
        }

        if (!in_array($postType, $this->allowedPostTypes, true)) {
            throw new InvalidArgumentException('Invalid post_type');
        }

        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        if ($coverImage instanceof UploadedFile) {
            $coverImageUrl = $this->uploadCoverImage($coverImage);
        }

        $stmt = $pdo->prepare($this->sqlUpdateById);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':excerpt', $excerpt, $excerpt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->bindValue(':post_type', $postType, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':cover_image_url', $coverImageUrl, $coverImageUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':seo_title', $seoTitle, $seoTitle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':seo_description', $seoDescription, $seoDescription === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_BOOL);
        $stmt->bindValue(':published_at', $publishedAt, $publishedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        $updatedPost = array_merge($post, [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
            'post_type' => $postType,
            'status' => $status,
            'cover_image_url' => $coverImageUrl,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'is_featured' => $isFeatured,
            'published_at' => $publishedAt,
        ]);

        return $this->render('integrations/article-update.html.twig', [
            'post' => $updatedPost,
            'form_action' => $this->generateUrl('integrations-update-post', ['id' => $id]),
        ]);
    }

    #[Route('/integrations/cardform', name: 'integrations-cardform', methods: ["GET"])]
    public function integrationResearchCardform(): Response
    {
        return $this->render('integrations/article.html.twig');
    }

    #[Route('/integrations/posts', name: 'integrations-posts', methods: ["GET"])]
    public function integrationResearchPosts(PDOService $pdoService): Response
    {
        $pdo = $pdoService->getConnection();
        $stmt = $pdo->prepare($this->sqlSelectAllPosts);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('integrations/article.posts.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/integrations/cardform/{id}', name: 'integrations-cardform-update', requirements: ['id' => '\d+'], methods: ["GET"])]
    public function integrationResearchCardformUpdate(PDOService $pdoService, int $id): Response
    {
        $pdo = $pdoService->getConnection();
        $post = $this->findPostById($pdo, $id);

        return $this->render('integrations/article-update.html.twig', [
            'post' => $post,
            'form_action' => $this->generateUrl('integrations-update-post', ['id' => $id]),
        ]);
    }

    private function findPostById(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare($this->sqlSelectById);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            throw $this->createNotFoundException(sprintf('Post with id %d not found', $id));
        }

        return $post;
    }

    private function uploadCoverImage(UploadedFile $coverImage): string
    {
        if (!$coverImage->isValid()) {
            throw new InvalidArgumentException('Cover image upload failed');
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/assets/posts';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Unable to create upload directory');
        }

        $extension = strtolower((string) $coverImage->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) pathinfo($coverImage->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        if ($extension === '' || !in_array($extension, $this->allowedImageExtensions, true)) {
            throw new InvalidArgumentException('Unsupported cover image format');
        }

        $safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '-', pathinfo($coverImage->getClientOriginalName(), PATHINFO_FILENAME));
        $safeBaseName = trim((string) $safeBaseName, '-');
        $safeBaseName = $safeBaseName !== '' ? strtolower($safeBaseName) : 'posts';
        $filename = sprintf('%s-%s.%s', $safeBaseName, bin2hex(random_bytes(6)), $extension);

        $coverImage->move($uploadDir, $filename);

        return '/assets/posts/' . $filename;
    }

    #[Route('/integrations/posts/{id}/delete', name: 'integrations-posts-delete', requirements: ['id' => '\d+'], methods: ["POST"])]
    public function integrationResearchDeletePost(Request $request, PDOService $pdoService, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete-post-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $pdo = $pdoService->getConnection();
        $this->findPostById($pdo, $id);

        $stmt = $pdo->prepare($this->sqlDeleteById);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $this->redirectToRoute('integrations-posts');
    }
}
