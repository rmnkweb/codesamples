<?php

namespace App\Http\Controllers\admin;

use App\Expert;
use App\Helpers\AdminNewsHelper;
use App\Helpers\DateHelper;
use App\Http\Controllers\FrontendController;
use App\Http\Requests\ArticleRequest;
use App\Photo_gallery;
use App\Repositories\EventlogRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\PeopleRepository;
use App\Repositories\TagsCategoriesRepository;
use App\Repositories\VideoRepository;
use App\Repositories\SubscribeRepository;
use App\Video;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use App\News;
use App\Subscribe;
use App\Repositories\NewsRepository;
use App\Repositories\TagsRepository;
use Illuminate\Http\Request;
use App\Helpers\GreedHelper;
use \App\Helpers\ContentStatuses as Statuses;

class NewsController extends FrontendController
{
    protected $model;
    protected $tags_cat_rep;
    protected $tags_rep;
    private $news_rep;
    private $p_rep;
    private $g_rep;
    private $sub_rep;
    private $elog_rep;
    private $helper;
    private $gallery;
    private $video;
    private $expert;
    private $dateHelper;
    protected $template;
    private $category = 'news';
    private $newsHelper;
    /**
     * @var VideoRepository
     */
    private $v_rep;


    public function __construct
    (
        News $news,
        Video $video,
        Expert $expert,
        Photo_gallery $gallery,
        TagsRepository $tags_rep,
        PeopleRepository $p_rep,
        NewsRepository $news_rep,
        EventlogRepository $elog_rep,
        SubscribeRepository $sub_rep,
        GreedHelper $helper,
        TagsCategoriesRepository $tags_cat_rep,
        AdminNewsHelper $newHelper,
        DateHelper $dateHelper,
        GalleryRepository $g_rep,
        VideoRepository $v_rep
    ) {
        $this->template = env('THEME') . 'admin.news.news';
        $this->model = $news;
        $this->tags_cat_rep = $tags_cat_rep;
        $this->helper = $helper;
        $this->dateHelper = $dateHelper;
        $this->tags_rep = $tags_rep;
        $this->p_rep = $p_rep;
        $this->gallery = $gallery;
        $this->expert = $expert;
        $this->video = $video;
        $this->g_rep = $g_rep;
        $this->news_rep = $news_rep;
        $this->elog_rep = $elog_rep;
        $this->sub_rep = $sub_rep;
        $this->newsHelper = $newHelper;
        $this->setTitle('Новости');

        parent::__construct();
        $this->v_rep = $v_rep;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (Gate::denies('articles.index', Auth::user())) {
            abort(403);
        }

        $select        = [];
        $order         = [ 'created_at', 'desc' ];
        $where         = [];
        $paginate      = 10;
        $relationWhere = [];

        $newsCategories = News::$categories;
        array_unshift($newsCategories, 'Все');

        $where[] = [
            'status',
            '!=',
            Statuses::STATUS_DRAFT
        ];

        if ($request->get('sortBy')) {
            $order[0] = $request->get('sortBy');

            if ($request->get($request->get('sortBy') . '_direction')) {
                $order[1] = $request->get($request->get('sortBy') . '_direction');
            }
        }

        if ($request->get('title')) {
            $where[] = [
                'title',
                'like',
                '%' . $request->get('title') . '%'
            ];
        }

        if ($request->get('category')) {
            $where[] = [
                'news_type',
                'like',
                '%' . $request->get('category') . '%'
            ];
        }

        if ($tag = $request->get('tag')) {
            $relationWhere['whereHas'][] = function ($query) use ($tag) {
                $query->where('tag', $tag);
            };
        }

        $data = $this->news_rep->getForAdminList($select, $order, $where, $paginate, $relationWhere);

        foreach ($data as $dataItem) {
            $dataItem->img  = json_decode($dataItem->img);
            $dataItem->date = $this->dateHelper->formatDate($dataItem->date);
        }

        $data->appends($request->except('page'));

        $this->attachVars([
            'data' => $data,
            'newsCategories' => $newsCategories
        ]);

        return $this->render();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function drafts(Request $request)
    {
        if (Gate::denies('articles.drafts', Auth::user())) {
            abort(403);
        }

        $select        = [];
        $order         = [ 'created_at', 'desc' ];
        $where         = [];
        $paginate      = 10;
        $relationWhere = [];

        $newsCategories = News::$categories;
        array_unshift($newsCategories, 'Все');

        $where['status'] = Statuses::STATUS_DRAFT;

        if ($request->get('sortBy')) {
            $order[0] = $request->get('sortBy');

            if ($request->get($request->get('sortBy') . '_direction')) {
                $order[1] = $request->get($request->get('sortBy') . '_direction');
            }
        }

        if ($request->get('title')) {
            $where[] = [
                'title',
                'like',
                '%' . $request->get('title') . '%'
            ];
        }

        if ($request->get('category')) {
            $where[] = [
                'news_type',
                'like',
                '%' . $request->get('category') . '%'
            ];
        }

        if ($tag = $request->get('tag')) {
            $relationWhere['whereHas'][] = function ($query) use ($tag) {
                $query->where('tag', $tag);
            };
        }

        $data = $this->news_rep->getForAdminList($select, $order, $where, $paginate, $relationWhere);

        foreach ($data as $dataItem) {
            $dataItem->img = json_decode($dataItem->img);
            $dataItem->date = $this->dateHelper->formatDate($dataItem->date);
        }

        $data->appends($request->except('page'));

        $this->attachVars([
            'data' => $data,
            'newsCategories' => $newsCategories
        ]);

        return $this->render();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Gate::denies('articles.create', Auth::user())) {
            abort(403);
        }

        $this->template = env('THEME') . 'admin.grid/grid';
        $sortedNews = [];
        $news = $this->news_rep->getFreshNews()->toArray();
        $tagsCategories = [];
        $tagsCategories['vyberite'] = 'Выберите';


        foreach ($this->tags_cat_rep->get()->toArray() as $i => $category) {
            $tagsCategories[str_slug($category['category'])] = $category['category'];
        }

        foreach ($news as $newsItem) {
            $sortedNews[$newsItem['id']] = $newsItem['title'];
        }

        $categories = $this->model::getCategories();
        $rel_id = md5(time());

        $galleries = $this->gallery->select('*')->where('status', Statuses::STATUS_ACTIVE)->orderBy('created_at',
            'desc')->paginate(10);
        $videos = $this->video->select('*')->where('status', Statuses::STATUS_ACTIVE)->orderBy('created_at',
            'desc')->paginate(10);
        $experts = $this->expert->select('*')->where('status', Statuses::STATUS_ACTIVE)->orderBy('created_at',
            'desc')->paginate(10);

        foreach ($galleries as $item) {
            $item->date = $this->dateHelper->formatDate($item->created_at);
            $item['links'] = (json_decode($item['links']));
            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);
        }


        $this->attachVars([
            'freshNews' => $sortedNews,
            'allFreshNews' => $news,
            'categories' => $categories,
            'galleries' => $galleries,
            'experts' => $experts,
            'videos' => $videos,
            'category' => $this->category,
            'rel_id' => $rel_id,
            'tagsCategories' => $tagsCategories,
//            'tagsCategoriesWithoutNewsType' => $tagsCategoriesWithoutNewsType,
            'newsCategories' => $categories
        ]);
        return $this->render();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return bool
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (Gate::denies('articles.store', $user)) {
            abort(403);
        }

        $data = $this->helper->processNewsData($request->input('formData'));

        if (isset($data['news_type'])) {
            $data['news_type'] = json_encode($data['news_type']);
        }

        if (isset($data['img'])) {
            $data['img'] = json_encode($data['img']);
        }

        if (empty($data['slug'])) {
            $data['slug'] = str_slug($data['title']);
        }

        $data['slug'] = str_slug($data['slug']);

        if (!$data['meta']['title']) {
            $data['meta']['title'] = $data['title'];
        }

        if (!$data['meta']['description']) {
            $data['meta']['description'] = strip_tags($data['sub_title']);
        }

        $data['meta'] = json_encode($data['meta']);
        $data['right_block'] = json_encode($data['right_block']);
        $data['slug'] = str_slug($data['title']);
//        $data['status'] = $this->model::STATUS_ACTIVE;
        $data['status'] = $data['status'] === 'true' ? $this->model::STATUS_ACTIVE : $this->model::STATUS_UNPUBLISHED;
        $data['created_by'] = $user->id;

        $data['date'] = date('Y-m-d H:i:s');

        $ifDup = $this->model::where(['rel_id' => $data['rel_id'], 'status' => $data['status']])->first();

        if ($ifDup) {
            return Response::json([
                'code' => 500,
                'message' => "Такая новость уже есть!",
                'route' => route('article.index'),
            ], 500);
        }

        if ($article = $this->model->create($data)) {

            \Session::flash('status', 'Новость добавлена!');

            $data['content'] = $this->helper->saveRelatedBlocks($data, $article, $user->id);

            if (isset($data['tags']) && count($data['tags']) > 0) {

                $tags = [];
                $categories_slugs = [];

                foreach ($data['tags'] as $item) {
                    $tag = $this->tags_rep->get('id', 1, ['tag' => $item['tag']])->first();

                    $tags[] = $tag === null ? $item['tag'] : $tag->id;
                    $categories_slugs[] = str_slug($item['category']);
                }

                $tagsIds = $this->tags_rep->saveTags($tags, $categories_slugs);
                $article->tags()->sync($tagsIds);
            }

            $article->update($data);
            $this->newsHelper->updateDraft($data);

            $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_STORE, $article->id);

            return Response::json([
                'code' => 200,
                'message' => 'Новость добавлена!',
                'route' => route('article.index'),
                'blocks' => $data['content']
            ], 200);
        };

        \Session::flash('status', 'Произошла ошибка!');

        return Response::json([
            'code' => 500,
            'message' => "Новость не добавлена!",
            'route' => route('article.index')
        ], 500);

//        return redirect(route('article.index'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->model->where('id', $id)->firstOrFail();
        $data = $this->newsHelper->getByIdForEdit($id);


        if (Gate::denies('articles.edit', Auth::user())) {
            abort(403);
        }

        $this->template = env('THEME') . 'admin.grid/grid';
        $sortedNews = [];
        $allFreshNews = $this->news_rep->getFreshNews()->toArray();

        $tagsCategories = [];
//        $tagsCategoriesWithoutNewsType = [];

        $tagsCategories['vyberite'] = 'Выберите';
//        $tagsCategoriesWithoutNewsType['vyberite'] = 'Выберите';
//        $tagsCategories['razdel-novosti'] = 'Раздел новости';

        foreach ($this->tags_cat_rep->get()->toArray() as $i => $category) {
            $tagsCategories[str_slug($category['category'])] = $category['category'];
//            $tagsCategoriesWithoutNewsType[str_slug($category['category'])] = $category['category'];
        }

        foreach ($allFreshNews as $newsItem) {
            $sortedNews[$newsItem['id']] = $newsItem['title'];
        }

//        dd($data['status']) === ($this->model::STATUS_ACTIVE || $this->model::STATUS_UNPUBLISHED);
        if ($data['status'] === $this->model::STATUS_ACTIVE) {
            $data['status'] = true;
        } elseif ($data['status'] === $this->model::STATUS_UNPUBLISHED) {
            $data['status'] = false;
        } else {
            $data['status'] = 0;
        }
//        $data['status'] = $data['status'] === $this->model::STATUS_DRAFT  ? false : true;
//        dd($data['status']);
        $rel_id = $data['rel_id'] ?? md5(time());
        $categories = $this->model::getCategories();

        $allTags = [];

        foreach ($tagsCategories as $i => $category) {

//            if($i === "razdel-novosti") {
//                $allTags['razdel-novosti'] = $categories;
//            } else {
            $tags = [];

            $cat = $this->tags_cat_rep->get(['id', 'category_slug'], false, ['category_slug' => $i])->first();
            $catId = isset($cat->id) ? $cat->id : null;

            foreach ($this->tags_rep->get(['id', 'tag'], false, ['category_id' => $catId])->toArray() as $item) {
                $tags[$item['id']] = $item['tag'];
            }

            natsort($tags);
            $allTags[$i] = $tags;
//            }
        }

        $allTags['vyberite'] = [''];

        $newsCategories = $tagsCategories;
        unset($newsCategories['proizvolnye']);

//        dd($data);
        $galleries = $this->gallery->select('*')->where('status', Statuses::STATUS_ACTIVE)->orderBy('created_at',
            'desc')->paginate(10);
        $videos = $this->video->select('*')->where([
            ['status', Statuses::STATUS_ACTIVE],
            ['category', 'video']
        ])->orderBy('created_at', 'desc')->paginate(10);
        $experts = $this->expert->select('*')->where('status', Statuses::STATUS_ACTIVE)->orderBy('created_at',
            'desc')->paginate(10);


        foreach ($galleries as $item) {
            $item->date = $this->dateHelper->formatDate($item->created_at);
            $item['links'] = (json_decode($item['links']));
            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);
        }

        $blocks = json_decode(htmlspecialchars_decode($data['content']));

        if (!$blocks) {
            $blocks = json_decode($data['content']);
        }

//        $blocks->block1->value = json_decode($blocks->block1->value);
//        dd($blocks);
        $data['content'] = htmlspecialchars(json_encode($blocks));
//        dd($data);
//        dd(($data['content']));
//        foreach ($blocks as $block) {
//
//        }


        $this->attachVars([
            'freshNews' => $sortedNews,
            'allFreshNews' => $allFreshNews,
            'data' => $data,
            'galleries' => $galleries,
            'experts' => $experts,
            'videos' => $videos,
            'tagsCategories' => $tagsCategories,
            'newsCategories' => $newsCategories,
            'category' => $this->category,
            'newsCategories' => $categories,
            'rel_id' => $rel_id,
            'allTags' => $allTags,
            'right_block' => $data['right_block']
        ]);
        return $this->render();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->input('formData');

        $activeNews = $this->model::where('id', $data['id'])->first();

        if (Gate::denies('articles.update', $user)) {
            abort(403);
        }

        if (isset($data['news_type'])) {
            $data['news_type'] = json_encode($data['news_type']);
        }


        if (!$data['meta']['title']) {
            $data['meta']['title'] = $data['title'];
        }

        if (!$data['meta']['description']) {
            $data['meta']['description'] = strip_tags($data['sub_title']);
        }

        $data['right_block'] = json_encode($data['right_block']);
        $data['meta'] = json_encode($data['meta']);

        if (empty($data['slug'])) {
            $data['slug'] = str_slug($data['title']);
        }

        $data['slug'] = str_slug($data['slug']);

        $data = $this->helper->processNewsData($data);

        if (isset($data['img'])) {
            $data['img'] = json_encode($data['img']);
        }

        $tags = [];
        $persons = [];
        $personsIds = [];
        $categories_slugs = [];

        if (isset($data['tags'])) {
            foreach ($data['tags'] as $item) {
                $tag = $this->tags_rep->get('id', 1, ['tag' => $item['tag']])->first();
                $tags[] = $tag === null ? $item['tag'] : $tag->id;

                $categories_slugs[] = str_slug($item['category']);

                if ($item['category'] === 'Персоналии') {
                    $persons[] = $item['tag'];
                }
            }
        }

        unset($data['tags']);


        $data['status'] = $data['status'] === 'true' ? $this->model::STATUS_ACTIVE : $this->model::STATUS_UNPUBLISHED;
        $data['news_type'] = isset($data['news_type']) ? $data['news_type'] : '["transneft"]';
        $data['content'] = $this->helper->saveRelatedBlocks($data, $activeNews, $user->id);

//        dump($data['content']);
//        die();
//
//        if(!$activeNews) {
//            $acrNews = new $this->model();
//            $acrNews->status = $this->model::STATUS_ACTIVE;
//            $acrNews->slug = str_slug($data['title']);
//            $acrNews->fill($data);
//            if(!$acrNews->save()) {
//                \Session::flash('status', 'Ошибка обновления!');
//
//                return Response::json([
//                    'code' => 500,
//                    'message' => 'Ошибка обновления!',
//                    'route' => route('article.index'),
//                ], 500);
//            }
//
//            $activeNews = $this->model::where(['rel_id' => $data['rel_id'], 'status' => $this->model::STATUS_DRAFT])->first();
//        } else {


        if ($activeNews->status === Statuses::STATUS_DRAFT && !$this->model->where('rel_id',
                $data['rel_id'])->where(function ($q) {
                $q->where('status', Statuses::STATUS_ACTIVE)
                    ->orWhere('status', Statuses::STATUS_UNPUBLISHED);
            })->exists()) {
            $activeNews = $this->model->create($data);
        } else if (
            $activeNews->status === Statuses::STATUS_DRAFT &&
            $activeOrPublishedNews = $this->model->where('rel_id',
                $data['rel_id'])->where(function ($q) {
                $q->where('status', Statuses::STATUS_ACTIVE)
                    ->orWhere('status', Statuses::STATUS_UNPUBLISHED);
            })->first()
        ) {
            $activeNews = $activeOrPublishedNews;
        }

        $activeNews->slug = str_slug($data['title']);

        if ($activeNews->update($data)) {
            $tagsIds = $this->tags_rep->saveTags($tags, $categories_slugs);
            $activeNews->tags()->sync($tagsIds);

            if (count($persons)) {
                foreach ($persons as $person) {
                    $id = $this->tags_rep->getIdBySlug(str_slug($person));
                    $personId = $this->p_rep->getPersonIdByTagId($id);

                    if ($personId) {
                        $personsIds[] = $personId;
                    }
                }

                $activeNews->persons()->sync($personsIds);
            }
        } else {
            \Session::flash('status', 'Ошибка обновления!');

            return Response::json([
                'code' => 500,
                'message' => 'Ошибка обновления!',
                'route' => route('article.index'),
            ], 500);

        }

        $data['status'] = $this->model::STATUS_DRAFT;
        $draft = $this->model::where(['rel_id' => $data['rel_id'], 'status' => $this->model::STATUS_DRAFT])->first();

        if ($draft) {
            $draft->update($data);
        } else {
            $draft = $activeNews;
            $draft->status = $this->model::STATUS_DRAFT;
            $draft->save();
        }

        $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_UPDATE, $draft->id);

        \Session::flash('status', 'Обновлено!');

        return Response::json([
            'code' => 200,
            'message' => 'Обновлено!',
            'route' => route('article.index'),
            'blocks' => $data['content'],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        if (Gate::denies('articles.destroy', Auth::user())) {
            abort(403);
        }

        $result = $this->news_rep->delete($id);
        $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_DESTROY, $id);

        if (is_array($result) && !empty($result['error'])) {
            return back()->with($result);
        }

        return redirect(route('article.index'))->with($result);
    }

    public function unPublish($id)
    {

        if (Gate::denies('articles.unPublish', Auth::user())) {
            abort(403);
        }

        $article = $this->model->select('*')->where(['id' => $id, 'status' => $this->model::STATUS_ACTIVE])->first();

        if (!$article) {
            throw new ModelNotFoundException();
        }

        $article->status = $this->model::STATUS_UNPUBLISHED;

        if ($article->save()) {
            $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_UNPUBLISH, $article->id);

            \Session::flash('status', 'Новость снята с публикации!');
            return redirect(route('article.index'));
        }

        \Session::flash('status', 'Ошибка!');
        return redirect(route('article.index'));
    }

    public function publish($id)
    {

        if (Gate::denies('articles.publish', Auth::user())) {
            abort(403);
        }

        $article = $this->model->select('*')->where([
            'id' => $id,
            'status' => $this->model::STATUS_UNPUBLISHED
        ])->firstOrFail();

        $article->status = $this->model::STATUS_ACTIVE;
        $article->popular = 0;

        if ($article->save()) {
            $categories = false;
            if ($article->news_type) {
                $categories = json_decode($article->news_type);
            }
            $subscribers = $this->sub_rep->getSubscribers($categories);
            $emails = [];
            foreach ($subscribers as $subscriber) {
                $emails[] = $subscriber->email;
            }
            $emails = array_unique($emails);

            $data = [
                "slug" => $article->slug,
                "title" => $article->title,
            ];

            // dd($emails);

            $fromAddress = env('MAIL_FROM_ADDRESS');
            $fromName = env('MAIL_FROM_NAME');
            foreach($emails as $email) {
                Mail::send('emails.publish', ["post" => $data, "email" => $email], function($m) use ($email, $fromAddress, $fromName) {
                    $m->from($fromAddress, $fromName);
                    $m->to($email)->subject('Рассылка Транснефть.Медиа');
                });
            }

            $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_PUBLISH, $article->id);

            \Session::flash('status', 'Новость опубликована!');
            return redirect(route('article.index'));
        }

        \Session::flash('status', 'Ошибка!');
        return redirect(route('article.index'));
    }


    public function saveDraft(Request $request)
    {
        if (Gate::denies('articles.saveDraft', Auth::user())) {
            abort(403);
        }

        $data = $request->input('formData');

        if (!isset($data['date'])) {
            $data['date'] = date('Y-m-d H:i:s');
        }

        $data['news_type'] = isset($data['news_type']) ? json_encode($data['news_type']) : json_encode('transneft');

        if (isset($data['img'])) {
            $data['img'] = json_encode($data['img']);
        }

        if (empty($data['slug'])) {
            $data['slug'] = str_slug($data['title']);
        }

        $data['slug'] = str_slug($data['slug']);

        if (!$data['meta']['title']) {
            $data['meta']['title'] = $data['title'];
        }

        if (!$data['meta']['description']) {
            $data['meta']['description'] = strip_tags($data['sub_title']);
        }

        $data['meta'] = json_encode($data['meta']);
        $data['status'] = $this->model::STATUS_DRAFT;
        $data['created_by'] = Auth::user()->id;
        $data['right_block'] = json_encode($data['right_block']);
        $news = $this->model->where('rel_id', $data['rel_id'])->where('status', $this->model::STATUS_DRAFT)->first();

        if (!$news) {
            $news = $this->model->create();
        }

//        $news->fill($data);

        if ($news->update($data)) {

            if (isset($data['tags']) && count($data['tags']) > 0) {

                $tags = [];
                $categories_slugs = [];

                foreach ($data['tags'] as $item) {
                    $tag = $this->tags_rep->get('id', 1, ['tag' => $item['tag']])->first();


                    $tags[] = $tag === null ? $item['tag'] : $tag->id;
                    $categories_slugs[] = str_slug($item['category']);
                }

                $tagsIds = $this->tags_rep->saveTags($tags, $categories_slugs);
                $news->tags()->sync($tagsIds);
            }

            $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_SAVEDRAFT, $news->id);

            return Response::json([
                'code' => 200,
            ], 200);
        }

        return Response::json([
            'code' => 500,
            'message' => 'Error'
        ], 500);
    }


    public function getPreview(Request $request)
    {

        $data = $request->input('newsData');
        $category = $request->input('category');

        $blockContent = json_decode($this->helper->processData($request->input('data')));

        $content = $this->helper->prepareContent($blockContent);

        $events = $this->events;
        $dictionary = $this->dictionary;
        $calendar = $this->calendar;
        $popularNews = $this->popularNews;

        if ($category === 'about_page') {
            $html = view(env('THEME') . 'admin.gridBlocks.previewAbout')->with(compact('content', 'data'))->render();
        } else {
            $html = view(env('THEME') . 'admin.gridBlocks.preview')->with(compact('content', 'data', 'events',
                'popularNews', 'calendar', 'dictionary'))->render();
        }


        return Response::json([
            'code' => 200,
            'html' => $html,
        ], 200);
    }

    public function getPreviewForBlock(Request $request)
    {
        $data = json_decode($request->input('data'));

        if (!isset($data->block1)) {
            return Response::json([
                'code' => 200,
                'html' => '',
            ], 200);
        }

        $newsByTag = [];
        $videosByTag = [];
        $blockType = $data->block1->blockType;


        if ($blockType === 'contentByTag') {
            $slug = str_slug($data->block1->value->tag[0]->tag);
            $newsByTag = $this->news_rep->getNewsBySlug($slug, false, 3, true);
            $videosByTag = $this->v_rep->getVideosByTag($slug);
        }

        if ($blockType === 'mini_article') {
            $slug = str_slug($data->block1->value->tag[0]->tag);
            $newsByTag = $this->news_rep->getNewsBySlug($slug, false, 2, true);
        }

        if ($blockType === 'projectGallery') {
            $blockType = 'photo_gallery';
        }

//        dump($data);
//        die;


        if ($blockType !== 'news' && $blockType !== 'custom_block') {
            $html = view(env('THEME') . 'admin.gridBlocks.' . $blockType)->with([
                'val' => $data->block1->value,
                'newsByTag' => $newsByTag,
                'videosByTag' => $videosByTag
            ])->render();

            return Response::json([
                'code' => 200,
                'html' => $html,
            ], 200);
        }
    }

    public function settings()
    {

        if (Gate::denies('articles.settings', Auth::user())) {
            abort(403);
        }

        $this->template = env('THEME') . 'admin.news.settings';
        $popular = $this->model->select('*')->take(4)->where('popular', '>', 0)->orderBy('popular', 'desc')->get();
        $items = $this->news_rep->get('*', false, ['status' => Statuses::STATUS_ACTIVE]);
        $allGalleries = [];
        $selected = [];
        $allGalleries[] = 'По-честному';

        foreach ($items as $item) {
            $allGalleries[$item->id] = $item->title;
        }

        foreach ($popular as $item) {
            $selected[$item->popular] = $item->id;
        }

        $this->attachVars(['selected' => $selected, 'popular' => $allGalleries]);
        return $this->render();
    }

    public function updatePopular(Request $request)
    {

        if (Gate::denies('articles.updatePopular', Auth::user())) {
            abort(403);
        }

        $data = $request->except('_token', '_method')['popular'];

        foreach ($data as $i => $item) {
            if ($item === '0') {
                $article = $this->model->where('popular', $i + 1)->first();
                if ($article) {
                    $article->popular = 0;
                    $article->save();
                }
            } else {
                $oldArticle = $this->model->where('popular', $i + 1)->first();
                if ($oldArticle) {
                    $oldArticle->popular = 0;
                    $oldArticle->save();
                }

                $article = $this->model->where('id', $item)->firstOrFail();
                $article->popular = $i + 1;
                $article->save();
            }
        }

        \Session::flash('status', 'Обновлено!');
        return redirect(route('article.index'));
    }

    public function trash(Request $request)
    {
        if (Gate::denies('articles.trash', Auth::user())) {
            abort(403);
        }

        $order = ['deleted_at', 'desc'];
        $sortByTitle = 'desc';
        $sortByDate = 'desc';
        $newsCategories = News::$categories;
        array_unshift($newsCategories, 'Все');
        $query = app('request')->except('page');

        if ($orderByTitle = $request->input('sortByTitle')) {
            $order = ['title', $orderByTitle];
            $query = app('request')->except(['sortByTitle', 'page']);
            $sortByTitle = $orderByTitle === 'desc' ? 'asc' : 'desc';
        }

        if ($orderByDate = $request->input('sortByDate')) {
            $order = ['date', $orderByDate];
            $query = app('request')->except(['sortByDate', 'page']);
            $sortByDate = $orderByDate === 'desc' ? 'asc' : 'desc';
        }


        $data = $this->model->getDeleted($order, $request);

        foreach ($data as $item) {
            $item->load('tags');
        }

        $this->vars = array_add($this->vars, 'newsCategories', $newsCategories);
        $this->vars = array_add($this->vars, 'data', $data);
        $this->vars = array_add($this->vars, 'query', $query);
        $this->vars = array_add($this->vars, 'sortByTitle', $sortByTitle);
        $this->vars = array_add($this->vars, 'sortByDate', $sortByDate);

        return $this->render();
    }

    public function restore($id)
    {
        if (Gate::denies('articles.restore', Auth::user())) {
            abort(403);
        }

        $item = $this->model->onlyTrashed()->where('id', $id)->firstOrFail();
        $item->deleted_at = null;
        $item->status = Statuses::STATUS_UNPUBLISHED;
        $item->save();
        $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_RESTORE, $item->id);
        \Session::flash('status', 'Материал восстановлен!');
        return redirect(route('article.index'));
    }

    public function forceDelete($id)
    {
        if (Gate::denies('articles.forceDelete', Auth::user())) {
            abort(403);
        }

        $item = $this->model->onlyTrashed()->where('id', $id)->firstOrFail();
        $item->forceDelete();
        $this->elog_rep->addLogRow($this->elog_rep::EVENTLOG_ENTITY_NEWS, $this->elog_rep::EVENTLOG_DELETE, $item->id);
        \Session::flash('status', 'Материал безвозвратно удален!');
        return redirect(route('article.trash'));
    }

    public function refreshBlockData(Request $request)
    {
        $block = json_decode($this->checkAjax($request)->input('values'));
        $data = $this->helper->refreshBlockData($block);

        return Response::json([
            'code' => 200,
            'block' => $data,
        ], 200);
    }

    public function searchInDb(Request $request)
    {
        $this->checkAjax($request);
        $take = 10;
        $query = $request->input('query');
        $page = $request->input('page');
        $blockType = $request->input('blockType');

        if ($blockType === 'projectGallery') {
            $blockType = 'photo_gallery';
        }

        $data = $this->helper->searchInDb($query, $blockType);
        $html = $this->helper->getHtmlForSearchTable($data->slice(($page - 1) * $take, $take), $blockType);

        $pagination = $this->helper->paginateCollection($data, $take, $page);

        return Response::json([
            'code' => 200,
            'html' => $html,
            'pagination' => $pagination->links('templates.blocks.paginationAjax')->toHtml()
        ], 200);
    }

    public function autocomplete(Request $request)
    {
        if ($request->ajax()) {
            $data = News::select("title as name")->where("title", "LIKE", "%{$request->input('query')}%")->get();
            return response()->json($data);
        } else {
            abort(403);
        }
    }
}
