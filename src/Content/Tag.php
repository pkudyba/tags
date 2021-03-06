<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Tags\Content;

use Flarum\Api\Client;
use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Frontend\Document;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;

class Tag
{
    /**
     * @var Client
     */
    protected $api;

    /**
     * @var Factory
     */
    protected $view;

    /**
     * @var TagRepository
     */
    protected $tags;

    /**
     * @param Client $api
     * @param Factory $view
     */
    public function __construct(Client $api, Factory $view, TagRepository $tags)
    {
        $this->api = $api;
        $this->view = $view;
        $this->tags = $tags;
    }

    public function __invoke(Document $document, Request $request)
    {
        $queryParams = $request->getQueryParams();
        $actor = $request->getAttribute('actor');

        $slug = Arr::pull($queryParams, 'slug');
        $sort = Arr::pull($queryParams, 'sort');
        $q = Arr::pull($queryParams, 'q', '');
        $page = Arr::pull($queryParams, 'page', 1);

        $sortMap = $this->getSortMap();

        $tagId = $this->tags->getIdForSlug($slug);
        $tag = $this->tags->findOrFail($tagId, $actor);

        $params = [
            'sort' => $sort && isset($sortMap[$sort]) ? $sortMap[$sort] : '',
            'filter' => [
                'q' => "$q tag:$slug"
            ],
            'page' => ['offset' => ($page - 1) * 20, 'limit' => 20]
        ];

        $apiDocument = $this->getApiDocument($actor, $params);

        $document->content = $this->view->make('tags::frontend.content.tag', compact('apiDocument', 'page', 'tag'));
        $document->payload['apiDocument'] = $apiDocument;

        return $document;
    }

    /**
     * Get a map of sort query param values and their API sort params.
     *
     * @return array
     */
    private function getSortMap()
    {
        return [
            'latest' => '-lastPostedAt',
            'top' => '-commentCount',
            'newest' => '-createdAt',
            'oldest' => 'createdAt'
        ];
    }

    /**
     * Get the result of an API request to list discussions.
     *
     * @param User $actor
     * @param array $params
     * @return object
     */
    private function getApiDocument(User $actor, array $params)
    {
        return json_decode($this->api->send(ListDiscussionsController::class, $actor, $params)->getBody());
    }
}
