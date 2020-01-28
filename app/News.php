<?php

namespace App;

use App\Helpers\CountViewsHelper;
use App\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Helpers\ContentStatuses as Statuses;
use Symfony\Component\HttpFoundation\Request;

class News extends Model
{
    use SoftDeletes;

    /**
     * Атрибуты, которые должны быть преобразованы в даты.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected $table = 'news';

    public static $categories = [
        'transneft' => 'Транснефть',
        'transport-nefti' => 'Транспорт нефти',
        'strategiya-razvitiya' => 'Стратегия развития',
        'korporativnaya-otvetstvennost' => 'Корпоративная ответственность',
        'lyudi' => 'Люди',
        'transneft.tv' => 'Транснефть.ТВ',
        'tek' => 'ТЭК',
        'invest-proekt' => 'Инвест проект',
    ];

    private static $routes = [
        'transneft' => 'index',
        'transport-nefti' => 'transport',
        'strategiya-razvitiya' => 'strategy',
        'korporativnaya-otvetstvennost' => 'responsibility',
        'lyudi' => 'people',
        'transneft.tv' => 'tv',
        'tek' => 'tek.articles',
        'invest-proekt' => 'projects',
    ];

    const CATEGORY_TRANSNEFT = 'transneft';
    const CATEGORY_TRANSPORT = 'transport-nefti';
    const CATEGORY_STRATEGY = 'strategiya-razvitiya';
    const CATEGORY_CORP = 'korporativnaya-otvetstvennost';
    const CATEGORY_PEOPLE = 'lyudi';
    const CATEGORY_TV = 'tv';
    const CATEGORY_TEK = 'tek';

    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_UNPUBLISHED = 2;

    public static function getCategories() {
        return self::$categories;
    }

    protected $fillable = ['created_at', 'title', 'img', 'description', 'content', 'slug', 'date', 'author', 'news_type',
        'contain_image', 'contain_video', 'sub_title', 'rel_id', 'photograph', 'textSearch', 'status', 'meta', 'right_block', 'created_by'];

    public function tags()
    {
        return $this->morphToMany('App\Tags', 'taggable', null, null, 'tag_id');
    }

    public function persons()
    {
        return $this->belongsToMany('App\People', 'news_people', 'news_id', 'people_id')
            ->withTimestamps();
    }

    public function galleries() {
        return $this->belongsToMany('App\Photo_gallery','news_galleries', 'news_id', 'gallery_id');
    }

    public function isTek() {
        if(strpos($this->news_type, 'tek') !== false) {
            return true;
        }
        return false;
    }

    public function formattedDate() {
        $dateHelper = new DateHelper();
        return $dateHelper->formatDate($this->date);
    }

    public function getCategory() {
        return self::$categories[json_decode($this->news_type)[0]];
    }

    public function getCategoriesForArticle() {
        $categories = [];

        if(is_array(json_decode($this->news_type))) {
            foreach (json_decode($this->news_type) as $category) {
                $categories[] = self::$categories[$category] ?? '';
            }
        }

        return $categories;
    }

    public function getSlug() {
        return self::$routes[json_decode($this->news_type)[0]];
    }

    public function getDeleted(array $order, Request $request) : LengthAwarePaginator {
        $builder = $this->select('*');

        if($title = $request->input('title')) {
            $builder->where('title', 'like', '%' . $title . '%');
        }

        if($category = $request->input('category')) {
            $builder->where('news_type', 'like', '%' . str_slug($category) . '%');
        }

        if($tag = $request->input('tag')) {
            $builder->whereHas('tags', function($q) use ($tag){
                $q->where('tag', $tag);
            });
        }
        return $builder->onlyTrashed()->orderBy($order[0], $order[1])->paginate(15);
    }

    public function getStatus() : string {
        return Statuses::getStatus($this);
    }

    public function countViews() {
        $countHelper = new CountViewsHelper();
        return $countHelper->countViews($this, 'view') . ' / ' . $countHelper->countViews($this, 'share');
    }

    public function countOnlyViews() {
        $countHelper = new CountViewsHelper();
        return $countHelper->countViews($this, 'view');
    }

    public function getShortName() {
        $user = User::where('id', $this->created_by)->first();
        $name = '';
        if($user) {
            $name = $user->getShortName();
        }

        return $name;
    }

    public function isPublished() {
        return $this->status === 1 ? true : false;
    }

}
