<?php

namespace BackBee\NestedNode\Tests;

use BackBee\NestedNode\Page;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBee\Tests\BackBeeTestCase;

/**
 * This class tests these classes to validate the page's url rewrite process:
 *     - BackBee\Event\Listener\RewritingListener
 *     - BackBee\NestedNode\Page
 *     - BackBee\Rewriting\UrlGenerator
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageUrlRewritingTest extends BackBeeTestCase
{
    private $urlGenerator;
    private $root;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->urlGenerator = self::$app->getContainer()->get('rewriting.urlgenerator');
    }

    public static function setUpBeforeClass()
    {
        self::$kernel->resetDatabase();

        $site = new Site();
        $site->setLabel('foobar');
        self::$em->persist($site);
        self::$em->flush($site);

        $layout = self::$kernel->createLayout('foobar');
        self::$em->persist($layout);
        self::$em->flush($layout);
    }

    public function setUp()
    {
        self::$em->clear();

        self::$kernel->resetDatabase([
            self::$em->getClassMetadata('BackBee\NestedNode\Page'),
        ]);

        $this->root = self::$kernel->createRootPage('test_page_url_rewriting');
        $this->root->setUrl('/');
        $this->root->setSite(self::$em->getRepository('BackBee\Site\Site')->findOneBy([]));
        $this->root->setLayout(self::$em->getRepository('BackBee\Site\Layout')->findOneBy([]));

        self::$em->persist($this->root);
        self::$em->flush($this->root);
    }

    public function testGenerateUrlOnNullOrEmpty()
    {
        // url === null tests
        $page = $this->generatePage('null');

        $this->assertNull($page->getUrl(false));

        self::$em->persist($page);
        self::$em->flush($page);

        $this->assertEquals('/null', $page->getUrl());

        // url === '' empty string tests
        $page = $this->generatePage('Empty string', '');

        $this->assertEquals('', $page->getUrl(false));

        self::$em->persist($page);
        self::$em->flush($page);

        $this->assertEquals('/empty-string', $page->getUrl());
    }

    public function testGenerateUniqueUrl()
    {
        $this->assertTrue($this->urlGenerator->isPreserveUnicity());
        $this->assertEquals('/backbee', $this->generatePage('backbee', null, true)->getUrl());
        $this->assertEquals('/backbee-1', $this->generatePage('backbee', null, true)->getUrl());
        $this->assertEquals('/backbee-2', $this->generatePage('backbee', null, true)->getUrl());
    }

    public function testReplaceOldDeletedUrl()
    {
        $this->assertTrue($this->urlGenerator->isPreserveUnicity());
        $pageToDelete = $this->generatePage('backbee', null, true);
        $otherPageToDelete = $this->generatePage('backbee', null, true);
        $this->assertEquals('/backbee', $pageToDelete->getUrl());
        $this->assertEquals('/backbee-1', $otherPageToDelete->getUrl());
        $this->assertEquals('/backbee-2', $this->generatePage('backbee', null, true)->getUrl());

        self::$em->remove($pageToDelete);
        self::$em->flush($pageToDelete);

        $this->assertNull(self::$em->getRepository('BackBee\NestedNode\Page')->findOneBy(['_url' => '/backbee']));

        $this->assertEquals('/backbee', $this->generatePage('backbee', null, true)->getUrl());

        self::$em->remove($otherPageToDelete);
        self::$em->flush($otherPageToDelete);

        $this->assertNull(self::$em->getRepository('BackBee\NestedNode\Page')->findOneBy(['_url' => '/backbee-1']));

        $this->assertEquals('/backbee-1', $this->generatePage('backbee', null, true)->getUrl());
    }

    public function testManualSetUrlAndPreserveUnicity()
    {
        $this->assertTrue($this->urlGenerator->isPreserveUnicity());
        $this->assertEquals('/foo/bar', $this->generatePage('backbee', '/foo/bar', true)->getUrl());
        $this->assertEquals('/foo/bar-1', $this->generatePage('backbee', '/foo/bar', true)->getUrl());
    }

    public function testUrlIsAutoGeneratedAsLongAsStateIsOfflineAndTitleChange()
    {
        $page = $this->generatePage('backbee', null, true);
        $this->assertEquals('/backbee', $page->getUrl());

        $page->setTitle('LP Digital');
        $this->assertEquals('/backbee', $page->getUrl());
        self::$em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        $page->setTitle('foo bar');
        $this->assertEquals('/lp-digital', $page->getUrl());
        self::$em->flush($page);
        $this->assertEquals('/foo-bar', $page->getUrl());
    }

    public function testChangeUrlOfPageOnlineWithPreserveOnline()
    {
        $this->assertTrue($this->urlGenerator->isPreserveOnline());
        $page = $this->generatePage('backbee', null, true);
        $page->setState(Page::STATE_OFFLINE);
        $this->assertEquals('/backbee', $page->getUrl());

        $page->setTitle('foo bar');
        self::$em->flush($page);
        $this->assertEquals('/foo-bar', $page->getUrl());

        // RewritingListener also detects if the previous page's state is equal to online or not to determine
        // if a very last autogenerate url is required
        $page->setState(Page::STATE_ONLINE);
        $page->setTitle('LP Digital');
        self::$em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        $page->setTitle('This is a test');
        self::$em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        // property preserveOnline only prevent RewritingListener and UrlGenerator to autogenerate url
        // but it's still possible to change manually the page url (no matters if preserveOnline is true or false)
        $page->setUrl('/nestednode-page');
        self::$em->flush($page);
        $this->assertEquals('/nestednode-page', $page->getUrl());
    }

    private function generatePage($title = 'backbee', $url = null, $doPersist = false)
    {
        $page = new Page();
        $page->setRoot($this->root);
        $page->setParent($this->root);
        $page->setTitle($title);
        $page->setUrl($url);

        if ($doPersist) {
            self::$em->persist($page);
            self::$em->flush($page);
        }

        return $page;
    }
}