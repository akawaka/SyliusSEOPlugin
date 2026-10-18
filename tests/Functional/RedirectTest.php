<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\Redirect;
use Akawaka\SyliusSEOPlugin\Enum\RedirectStatus;

final class RedirectTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM ' . Redirect::class)->execute();

        parent::tearDown();
    }

    public function testUnknownUrlIsRedirectedAndHitsAreCounted(): void
    {
        $redirect = new Redirect();
        $redirect->setSourcePath('/en_US/old-page');
        $redirect->setTargetUrl('/en_US/');
        $redirect->setStatus(RedirectStatus::Temporary);
        $this->entityManager()->persist($redirect);
        $this->entityManager()->flush();

        $this->client->request('GET', '/en_US/old-page');

        self::assertResponseRedirects('/en_US/', 302);
        $this->entityManager()->clear();
        self::assertSame(1, $this->entityManager()->getRepository(Redirect::class)->findOneBy(['sourcePath' => '/en_US/old-page'])->getHits());
    }

    public function testDisabledRedirectIsIgnored(): void
    {
        $redirect = new Redirect();
        $redirect->setSourcePath('/en_US/disabled');
        $redirect->setTargetUrl('/en_US/');
        $redirect->setEnabled(false);
        $this->entityManager()->persist($redirect);
        $this->entityManager()->flush();

        $this->client->request('GET', '/en_US/disabled');

        self::assertResponseStatusCodeSame(404);
    }

    public function testChangingAProductSlugCreatesAPermanentRedirect(): void
    {
        $translation = $this->firstProduct()->getTranslation('en_US');
        $id = $translation->getId();
        $oldSlug = (string) $translation->getSlug();
        $repository = $this->entityManager()->getRepository(Redirect::class);

        try {
            $translation->setSlug('brand-new-slug');
            $this->entityManager()->flush();

            $redirect = $repository->findOneBy(['sourcePath' => '/en_US/products/' . $oldSlug]);
            self::assertInstanceOf(Redirect::class, $redirect);
            self::assertSame('/en_US/products/brand-new-slug', $redirect->getTargetUrl());
            self::assertSame(RedirectStatus::Permanent, $redirect->getStatus());
            self::assertTrue($redirect->isAutomatic());

            // Moving back removes the redirection that would now loop.
            $translation->setSlug($oldSlug);
            $this->entityManager()->flush();
            self::assertNull($repository->findOneBy(['sourcePath' => '/en_US/products/' . $oldSlug]));
            self::assertNotNull($repository->findOneBy(['sourcePath' => '/en_US/products/brand-new-slug']));
        } finally {
            $this->entityManager()->clear();
            $this->entityManager()->createQuery('UPDATE ' . $translation::class . ' t SET t.slug = :slug WHERE t.id = :id')
                ->setParameters(['slug' => $oldSlug, 'id' => $id])->execute();
        }
    }

    public function testRedirectionsAreManagedFromTheAdmin(): void
    {
        $this->logInAsAdministrator();

        $crawler = $this->client->request('GET', '/admin/seo/redirects/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="akawaka_seo_redirect"], form[name="redirect"]')->form();
        $prefix = $form->getName();
        $form[$prefix . '[sourcePath]'] = '/en_US/from-admin';
        $form[$prefix . '[targetUrl]'] = '/en_US/';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/admin/seo/redirects/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/en_US/from-admin', $crawler->text());
    }
}
