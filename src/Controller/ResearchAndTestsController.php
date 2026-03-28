<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\PDOService;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ResearchAndTestsController extends AbstractController
{
    private string $sql = "
        SELECT
            bp.slug,
            bp.title,
            bp.excerpt,
            bp.cover_image_url,
            bp.seo_title,
            bp.seo_description,
            bp.published_at
        FROM public.blog_posts bp
        WHERE bp.status = 'published'
        AND (bp.published_at IS NULL OR bp.published_at <= NOW()) ORDER BY bp.id DESC
    ";

    private string $postSql = "
        SELECT
            bp.slug,
            bp.title,
            bp.excerpt,
            bp.content,
            bp.cover_image_url,
            bp.seo_title,
            bp.seo_description,
            bp.published_at
        FROM public.blog_posts bp
        WHERE bp.slug = :slug
          AND bp.status = 'published'
          AND (bp.published_at IS NULL OR bp.published_at <= NOW())
        LIMIT 1
    ";

    #[Route('/resnt', name: 'resnt', methods: ["GET"])]
    public function resntStart(PDOService $pdoService, CacheInterface $cache): Response
    {

        $html = $cache->get('research', function (ItemInterface $item) use ($pdoService): string {
            $item->expiresAfter(300);
            $pdo = $pdoService->getConnection();
            $stmt = $pdo->prepare($this->sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return $this->renderView('research/index.html.twig', [
                'articles' => $rows,
            ]);
        });
        return new Response($html);
    }

    #[Route('/post/{slug}', name: 'resnt_post', methods: ["GET"])]
    public function resntPost(string $slug, PDOService $pdoService, CacheInterface $cache): Response
    {
        $html = $cache->get('research_post_' . $slug, function (ItemInterface $item) use ($pdoService, $slug): string {
            $item->expiresAfter(300);
            $pdo = $pdoService->getConnection();
            $stmt = $pdo->prepare($this->postSql);
            $stmt->bindValue(':slug', $slug, \PDO::PARAM_STR);
            $stmt->execute();
            $post = $stmt->fetch();

            if (!$post) {
                throw $this->createNotFoundException('Post not found');
            }

            return $this->renderView('research/post.html.twig', [
                'post' => $post,
            ]);
        });

        return new Response($html);
    }

}
