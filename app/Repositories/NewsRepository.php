<?php

namespace App\Repositories;

use App\Helpers\ContentStatuses;
use App\Helpers\DateHelper;
use App\News;
use App\People;
use App\Tags;
use App\Mobile_Detect;

use Illuminate\Database\Eloquent\Collection as Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Request;

use Illuminate\Contracts\Cache;

class NewsRepository extends Repository
{

    /**
     * @var News
     */
    protected $model;

    protected $tagModel;
    private $dateHelper;
    private $p_rep;
    private $r_rep;

    public function __construct(
        News $news,
        Tags $tags,
        DateHelper $dateHelper,
        PeopleRepository $p_rep,
        RegionsRepository $r_rep
    ) {
        $this->model = $news;
        $this->tagModel = $tags;
        $this->p_rep = $p_rep;
        $this->r_rep = $r_rep;
        $this->dateHelper = $dateHelper;
    }

    public function textSearch(string $search, string $category, int $limit, int $offset = 0)
    {
        $newsByTag = [];
        $newsIds = [];

        $builder = $this->model->select('*')
            ->where(function ($q) use ($search, $category) {
                $q
                    ->where('title', 'like', $search . '%')
                    ->orWhere('title', 'like', '% ' . $search . '%')
//                    ->orWhere('textSearch', 'like', $search . '%')
                    ->orWhere('textSearch', 'like', '% ' . $search . '%');

                // if ($category !== '') {
                //     $q->where('news_type', $category);
                // }
            })
            ->where('status', $this->model::STATUS_ACTIVE);
        if($category) {
            $builder->where('news_type', str_slug($category));
        }

        $news = $builder->take($limit)->offset($offset)->get();


        $tagId = $this->tagModel->select('id')->where('slug', str_slug($search))->first();

        if ($tagId) {
            $newsByTag = $this->model->whereHas('tags', function ($q) use ($tagId) {
                $q->where('tag_id', $tagId->id);
            })->where('status', $this->model::STATUS_ACTIVE)->take($limit)->offset($offset)->get();
        }

        foreach ($news as $item) {
            $newsIds[] = $item->id;
        }

        foreach ($newsByTag as $i => $item) {
            if (in_array($item->id, $newsIds)) {
                unset($newsByTag[$i]);
            } else {
                $news->push($newsByTag[$i]);
            }
        }

        foreach ($news as $item) {
            $item->img = json_decode($item->img);

            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);

            $item->date = $this->dateHelper->formatDate($item->date);
        }


        return $news;
    }

    public function getNewsBySlug(
        $slug = '',
        string $category = '',
        int $limit = 4,
        bool $withTags = false,
        $offset = false
    ) {

        if (!$slug) {
            return [];
        }

        if (is_string($slug)) {
            $id = $this->tagModel->select('id')->where('slug', $slug)->first();
            if ($id) {
                $slug = $id->id;
            }
        }

        $builder = $this->model->whereHas('tags', function ($q) use ($slug) {
            $q->where('tag_id', $slug);
        });

        if ($category !== '') {
            $builder->where('news_type', $category);
        }

        $builder->where('status', $this->model::STATUS_ACTIVE);

        if ($offset) {
            $builder->offset($offset);
        }

        $builder->orderBy('created_at', 'desc');

        $news = $builder->take($limit)->get();


        foreach ($news as $item) {
            $item->img = json_decode($item->img);
            $item->date = $this->dateHelper->formatDate($item->date);

            if ($withTags) {
                $item->load([
                    'tags' => function ($q) {
                        $q->take(3);
                    }
                ]);
            }
        }


        return $news;
    }


    public function getMoreNews($category, $limit = 3, $offset = 0)
    {
        $builder = $this->model->select('*');

        if ($category !== false) {
            $builder->where('news_type', 'like', '%' . $category . '%');
        }

        $builder->where('status', $this->model::STATUS_ACTIVE);

        $news = $builder->take($limit)->offset($offset)->orderBy('date', 'desc')->get();

        foreach ($news as $item) {
            $item->img = json_decode($item->img);
            $item->date = $this->dateHelper->formatDate($item->date);
        }

        return $news;
    }


    /**
     * @param $tags
     * @param $newsId
     * @return array
     */
    public function getRelatedNews($tags, $newsId)
    {
        $ids = [];
        $related_news = [];

        foreach ($tags as $tag) {
            $items = $this->model->whereHas('tags', function ($q) use ($tag) {
                $q->where('tag_id', $tag->id);
            })->where('status', ContentStatuses::STATUS_ACTIVE)->orderBy('date', 'desc')->with('tags')->get();

            foreach ($items as $item) {
                $counter = 0;
                $item->img = json_decode($item->img);
                $item->date = $this->dateHelper->formatDate($item->date);

                foreach ($item->tags as $tagItem) {
                    if ($tag->tag === $tagItem->tag) {
                        $counter++;
                        $ids[$item->id][] = [
                            'tag' => $tagItem->tag,
                            'id' => $item->id,
                            'date' => $item->date,
                            'news' => $item
                        ];
                    }
                }
            }
        }

        unset($ids[$newsId]);

        $ids = array_splice($ids, 0, 3);

        usort($ids, function ($a, $b) {
            if (count($a) === count($b)) {

                if ($a[0]['date'] === $b[0]['date']) {
                    return 0;
                }

                return ($a[0]['date'] > $b[0]['date']) ? -1 : 1;;
            }

            return (count($a) > count($b)) ? -1 : 1;
        });

        foreach ($ids as $i => $item) {
            $related_news[$i] = $item[0]['news'];
            $related_news[$i]->tags = $related_news[$i]->tags->splice(0, 3);
        }

        return $related_news;
    }


    public function getFreshNews()
    {
        return $this->model->select([
            'id',
            'img',
            'title',
            'sub_title',
            'contain_video',
            'contain_image',
            'date',
            'author'
        ])->where('status', $this->model::STATUS_ACTIVE)->take(50)->orderBy('created_at', 'desc')->with('tags')->get();
    }

    /**
     * @param $data
     * @param $id
     * @return array|\Illuminate\Http\RedirectResponse
     */
    public function update($data, $id)
    {

        if (empty($data)) {
            return ['error' => 'Нет данных'];
        }

        $item = $this->findById($id);

        if ($item->update($data)) {
            return ['status' => 'Материал обновлен'];
        }

        return redirect()->back();
    }

    /**
     * @param $slug
     * @return array
     */
    public function getNewsByPerson($slug)
    {
        $person = $this->p_rep->get('*', 1, ['slug' => $slug])->first();
        $data   = [];

        if ($person) {
            $person->load('tags');

            foreach ($person->tags as $tag) {
                $data = $this->model::whereHas('tags', function ($q) use ($tag) {
                    $q->where('slug', $tag->slug);
                })->where('status', 1)->limit(3)->get();
            }
        }

        if ($data) {
            foreach ($data as $item) {
                $item['img'] = json_decode($item['img']);

                $item->load([
                    'tags' => function ($query) {
                        $query->take(3);
                    }
                ]);

                $item['date'] = $this->dateHelper->formatDate($item['date']);
            }
        }

        return $data;
    }

    public function getNewsByRegion($slug, $limit = false, $offset = false)
    {
        $region = $this->r_rep->get('*', 1, ['slug' => $slug])->first();
        $news = [];

        if ($region) {
            $region->load('tags');

            foreach ($region->tags as $tag) {
                $builder = $this->model->where('status', 1);

                $builder->whereHas('tags', function ($query) use ($tag) {
                    $query->where('slug', $tag->slug);
                });

                if ($offset) {
                    $builder->offset($offset);
                }

                if ($limit) {
                    $builder->limit($limit);
                }

                $data = $builder->get();

                foreach ($data as $item) {
                    $item['img'] = json_decode($item['img']);

                    $item->load([
                        'tags' => function ($query) {
                            $query->take(3);
                        }
                    ]);

                    $item['date'] = $this->dateHelper->formatDate($item['date']);
                    $news[] = $item;
                }
            }
        }

        return $news;
    }

    public function get($select = '*', $take = false, $where = false, $orderBy = false, $paginate = false, $offset = 0)
    {
        if (!$orderBy) {
            $orderBy = ['date', 'desc'];
        }

        $news = parent::get($select, $take, $where, $orderBy, $paginate, $paginate = false, $offset);


        foreach ($news as $item) {
            $item->date = $this->dateHelper->formatDate($item->date);

            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);

            $item->img = json_decode($item->img);
        }

        return $news;
    }

    public function getCustom($select = '*', $take = false, $where = false, $orderBy = false, $paginate = false, $offset = 0)
    {
        if (!$orderBy) {
            $orderBy = ['date', 'desc'];
        }

        $news = parent::get($select, $take, $where, $orderBy, $paginate, $paginate = false, $offset);


        foreach ($news as $item) {
            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);

            $item->img = json_decode($item->img);
        }

        return $news;
    }

    public function getNewsForAdminList(
        $select = '*',
        $take = 16,
        $where = [],
        $orderBy = ['created_at', 'desc'],
        Request $request
    ) {
        $builder = $this->model->select($select)->orderBy($orderBy[0], $orderBy[1]);

        if ($title = $request->input('title')) {
            $builder->where('title', 'like', '%' . $title . '%');
        }

        if ($category = $request->input('category')) {
            $builder->where('news_type', 'like', '%' . str_slug($category) . '%');
        }

        if ($tag = $request->input('tag')) {
            $builder->whereHas('tags', function ($q) use ($tag) {
                $q->where('tag', $tag);
            });
        }

        if (count($where)) {
            $builder->where($where[0], $where[1], $where[2]);
        }

        $news = $builder->paginate($take)->appends(request()->except('page'));

        foreach ($news as $item) {
            $item->date = $this->dateHelper->formatDate($item->date);

            $item->load([
                'tags' => function ($q) {
                    $q->take(3);
                }
            ]);

            $item->img = json_decode($item->img);
        }

        return $news;
    }

    public function getNewsByCategory($category, $select = "*", $limit = 6, $offset = 0, $slug = false)
    {
        $data = $this->model->select($select)->where('status',
            $this->model::STATUS_ACTIVE)->limit($limit)->offset($offset)->orderBy('date', 'desc');

        if ($category && $category !== 'all') {
            $data->where('news_type', 'like', '%"' . $category . '"%');
        }

        if ($slug) {
            $data->whereHas('tags', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        $data = $data->get();

        foreach ($data as $item) {
            $item->date = $this->dateHelper->formatDate($item->date);

            $item->load([
                'tags' => function ($query) {
                    $query->take(3);
                }
            ]);

            $item->img = json_decode($item->img);
        }

        return $data;
    }

    public function getNewsExceptCategory($category, $select = "*", $limit = 6, $offset = 0, $slug = false, $where = [])
    {
        $data = $this->model->select($select)->where('status',
            $this->model::STATUS_ACTIVE)->limit($limit)->offset($offset);

        if ($category && $category !== 'all') {
            $data->where('news_type', 'not like', '%"' . $category . '"%');
        }

        if ($slug) {
            $data->whereHas('tags', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        if (count($where) > 0) {
            $data->where($where[0], $where[1], $where[2]);
        }

        $data = $data->orderBy('date', 'desc')->get();

        foreach ($data as $item) {
            $item->date = $this->dateHelper->formatDate($item->date);

            $item->load([
                'tags' => function ($query) {
                    $query->take(3);
                }
            ]);

            $item->img = json_decode($item->img);
        }

        return $data;
    }

}
