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
    private array $allowedPostTypes = ['research', 'test', 'news'];
    private array $allowedStatuses = ['draft', 'published', 'archived'];
    private array $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

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

    #[Route('/integrations/cardform', name: 'integrations-cardform', methods: ["GET"])]
    public function integrationResearchCardform(): Response
    {
        return $this->render('integrations/article.html.twig');
    }
}
