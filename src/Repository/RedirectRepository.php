<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Akawaka\SyliusSEOPlugin\Entity\RedirectInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class RedirectRepository extends EntityRepository
{
    public function findEnabledBySourcePath(string $path): ?RedirectInterface
    {
        $redirect = $this->findOneBy(['sourcePath' => $path, 'enabled' => true]);

        return $redirect instanceof RedirectInterface ? $redirect : null;
    }
}
