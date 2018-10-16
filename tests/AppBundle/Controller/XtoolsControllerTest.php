<?php
/**
 * This file contains only the XtoolsControllerTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\SimpleEditCounterController;
use AppBundle\Controller\XtoolsController;
use AppBundle\Exception\XtoolsHttpException;
use AppBundle\Helper\I18nHelper;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Integration/unit tests for the abstract XtoolsController.
 * @group integration
 */
class XtoolsControllerTest extends ControllerTestAdapter
{
    /** @var I18nHelper Needed by SimpleEditCounterController. */
    protected $i18n;

    /** @var XtoolsController The controller. */
    protected $controller;

    /**
     * Set up the tests.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->i18n = $this->container->get('app.i18n_helper');
    }

    /**
     * Create a new controller, making a Request with the given params.
     * @param array $params
     * @return XtoolsController
     */
    private function getControllerWithRequest(array $params = []): XtoolsController
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request($params));

        // SimpleEditCounterController used solely for testing, since we
        // can't instantiate the abstract class XtoolsController.
        return new SimpleEditCounterController($requestStack, $this->container, $this->i18n);
    }

    /**
     * Make sure all parameters are correctly parsed.
     * @dataProvider paramsProvider
     * @param array $params
     * @param array $expected
     */
    public function testParseQueryParams(array $params, array $expected): void
    {
        // Untestable on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $controller = $this->getControllerWithRequest($params);
        $result = $controller->parseQueryParams();
        static::assertEquals($expected, $result);
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return string[]
     */
    public function paramsProvider(): array
    {
        return [
            [
                // Modern parameters.
                [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'page' => 'Test',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'page' => 'Test',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ],
            ], [
                // Legacy parameters mixed with modern.
                [
                    'project' => 'enwiki',
                    'user' => 'GoldenRing',
                    'namespace' => '0',
                    'article' => 'Test',
                ], [
                    'project' => 'enwiki',
                    'username' => 'GoldenRing',
                    'namespace' => '0',
                    'page' => 'Test',
                ],
            ], [
                // Missing parameters.
                [
                    'project' => 'en.wikipedia',
                    'page' => 'Test',
                ], [
                    'project' => 'en.wikipedia',
                    'page' => 'Test',
                ],
            ], [
                // Legacy style.
                [
                    'wiki' => 'wikipedia',
                    'lang' => 'de',
                    'article' => 'Test',
                    'name' => 'Bob Dylan',
                    'begin' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'de.wikipedia.org',
                    'page' => 'Test',
                    'username' => 'Bob Dylan',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ],
            ], [
                // Legacy style with metawiki.
                [
                    'wiki' => 'wikimedia',
                    'lang' => 'meta',
                    'page' => 'Test',
                ], [
                    'project' => 'meta.wikimedia.org',
                    'page' => 'Test',
                ],
            ], [
                // Legacy style of the legacy style.
                [
                    'wikilang' => 'da',
                    'wikifam' => '.wikipedia.org',
                    'page' => '311',
                ], [
                    'project' => 'da.wikipedia.org',
                    'page' => '311',
                ],
            ], [
                // Language-neutral project.
                [
                    'wiki' => 'wikidata',
                    'lang' => 'fr',
                    'page' => 'Q12345',
                ], [
                    'project' => 'wikidata.org',
                    'page' => 'Q12345',
                ],
            ], [
                // Language-neutral, ultra legacy style.
                [
                    'wikifam' => 'wikidata',
                    'wikilang' => 'fr',
                    'page' => 'Q12345',
                ], [
                    'project' => 'wikidata.org',
                    'page' => 'Q12345',
                ],
            ],
        ];
    }

    /**
     * Getting a Project from the project query string.
     */
    public function testProjectFromQuery(): void
    {
        // Untestable on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $controller = $this->getControllerWithRequest(['project' => 'de.wiktionary.org']);
        static::assertEquals(
            'de.wiktionary.org',
            $controller->getProjectFromQuery()->getDomain()
        );

        $controller = $this->getControllerWithRequest();
        static::assertEquals(
            'en.wikipedia.org',
            $controller->getProjectFromQuery()->getDomain()
        );
    }

    /**
     * Validating the project and user parameters.
     */
    public function testValidateProjectAndUser(): void
    {
        // Untestable on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $controller = $this->getControllerWithRequest([
            'project' => 'fr.wikibooks.org',
            'username' => 'MusikAnimal',
            'namespace' => '0',
        ]);

        $project = $controller->validateProject('fr.wikibooks.org');
        static::assertEquals('fr.wikibooks.org', $project->getDomain());

        $user = $controller->validateUser('MusikAnimal');
        static::assertEquals('MusikAnimal', $user->getUsername());

        static::expectException(XtoolsHttpException::class);
        $controller->validateUser('Not a real user 8723849237');

        static::expectException(XtoolsHttpException::class);
        $controller->validateProject('invalid.project.org');

        // Too high of an edit count.
        static::expectException(XtoolsHttpException::class);
        $controller->validateUser('Materialscientist');
    }

    /**
     * Make sure standardized params are properly parsed.
     */
    public function testGetParams(): void
    {
        // Untestable on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $controller = $this->getControllerWithRequest([
            'project' => 'enwiki',
            'username' => 'Jimbo Wales',
            'namespace' => '0',
            'article' => 'Foo',
            'redirects' => '',
        ]);

        static::assertEquals([
            'project' => 'enwiki',
            'username' => 'Jimbo Wales',
            'namespace' => '0',
            'article' => 'Foo',
        ], $controller->getParams());
    }

    /**
     * Validate a page exists on a project.
     */
    public function testValidatePage(): void
    {
        // Untestable on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $controller = $this->getControllerWithRequest(['project' => 'enwiki']);
        static::expectException(XtoolsHttpException::class);
        $controller->validatePage('Test adjfaklsdjf');

        static::assertInstanceOf(
            'Xtools\Page',
            $controller->validatePage('Bob Dylan')
        );
    }

    /**
     * Converting start/end dates into UTC timestamps.
     */
    public function testUTCFromDateParams(): void
    {
        $controller = $this->getControllerWithRequest();

        static::assertEquals(
            [1483228800, 1501545600],
            $controller->getUTCFromDateParams(
                '2017-01-01',
                '2017-08-01'
            )
        );

        static::assertEquals(
            [1498867200, 1501545600],
            $controller->getUTCFromDateParams(
                false,
                '2017-08-01',
                true // Use default, -1 month from end date.
            )
        );

        static::assertEquals(
            [1501545600, 1504224000],
            $controller->getUTCFromDateParams(
                '2017-09-01',
                '2017-08-01'
            )
        );

        // Without using defaults.
        static::assertEquals(
            [false, 1501545600],
            $controller->getUTCFromDateParams(
                null,
                '2017-08-01',
                false
            )
        );

        static::assertEquals(
            [1501545600, 1504224000],
            $controller->getUTCFromDateParams(
                '2017-09-01',
                '2017-08-01',
                false
            )
        );
    }

    /**
     * Test involving fetching and settings cookies.
     */
    public function testCookies(): void
    {
        $crawler = $this->client->request('GET', '/sc');
        static::assertEquals(
            $this->container->getParameter('default_project'),
            $crawler->filter('#project_input')->attr('value')
        );

        // For now...
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $cookie = new Cookie('XtoolsProject', 'test.wikipedia');
        $this->client->getCookieJar()->set($cookie);

        $crawler = $this->client->request('GET', '/sc');
        static::assertEquals('test.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

        $this->client->request('GET', '/sc/enwiki/Example');
        static::assertEquals(
            'en.wikipedia.org',
            $this->client->getResponse()->headers->getCookies()[0]->getValue()
        );
    }
}
