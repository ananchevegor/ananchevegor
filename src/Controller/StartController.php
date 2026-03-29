<?php
namespace App\Controller;

use PDO;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Service\PDOService;


class StartController extends AbstractController
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
        AND (bp.published_at IS NULL OR bp.published_at <= NOW()) ORDER BY bp.id DESC LIMIT 3
    ";


    #[Route('/', name: 'start')]
    public function start(CacheInterface $cache, PDOService $pdoService): Response
    {
        $html = $cache->get('start_page', function (ItemInterface $item) use ($pdoService): string {
            $item->expiresAfter(300);
            $pdo = $pdoService->getConnection();
            $stmt = $pdo->prepare($this->sql);
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->renderView('start.html.twig', ['posts' => $posts]);
        });
        return new Response($html);
    }

    #[Route('/about', name: 'about')]
    public function about(CacheInterface $cache): Response
    {
        
        $html = $cache->get('about_page', function (ItemInterface $item): string {
            $item->expiresAfter(3600);
            return $this->renderView('about/index.html.twig');
        });
        return new Response($html);
    }

}

