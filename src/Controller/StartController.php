<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StartController extends AbstractController
{
    #[Route('/', name: 'start')]
    public function start(CacheInterface $cache): Response
    {
        $html = $cache->get('start_page', function (ItemInterface $item): string {
            $item->expiresAfter(300);
            return $this->renderView('start.html.twig');
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

