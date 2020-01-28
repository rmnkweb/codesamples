<?php

namespace App\Helpers;

use App\News;
use App\Repositories\GalleryRepository;
use App\Repositories\NewsRepository;
use App\Helpers\ModelsFactory;


class AdminNewsHelper
{
    private $model;
    private $rep;
    private $g_rep;

    public function __construct(News $news, NewsRepository $rep, GalleryRepository $g_rep)
    {
        $this->model = $news;
        $this->rep = $rep;
        $this->g_rep = $g_rep;
    }

    public function getByIdForEdit($id) {
        $article = $this->rep->getCustom('*')->where('id', $id)->first();

        if($article) {
            $article->load('tags');
            $article->tags->load('category');
            $article = $article->toArray();

            $article['news_type'] = json_decode($article['news_type']) === null ? [] : json_decode($article['news_type']);

            foreach ($article['news_type'] as $i => $item) {
                $article['news_type'][$i] = [
                    'id' => $item,
                    'category_id' => 'razdel-novosti'
                ];
            }

            $blocks = json_decode($article['content']);

            if(!$blocks) {
                return $article;
            }

            foreach ($blocks as $i => $block) {
                if($block->needToSave === true && isset($block->id)) {
                    $factory = new ModelsFactory();
                    $model = $factory->make(ucfirst($block->blockType));
                    $blockData = $model->select('*')->where('id', $block->id)->first();
                    if($blockData) {
                        $blockData = $blockData->toArray();

                        foreach ($blockData as $k => $v) {
                            if(isset($block->value->{$k})) {
                                if($k === 'tags') {
                                    $blocks->{$i}->value->{$k} = json_decode($blockData[$k]);
                                } else {
                                    $blocks->{$i}->value->{$k} = $blockData[$k];
                                }

                            }

                            if($block->blockType === 'photo_gallery') {
                                if($block->id) {
                                    $gallery = $this->g_rep->get('*', 1, ['id' => $block->id])->first();

                                    if($gallery) {
                                        $blocks->{$i}->value->title = $gallery->title;
                                        $blocks->{$i}->value->text = $gallery->text;
                                        $blocks->{$i}->value->links = json_decode($gallery->links);
                                        $blocks->{$i}->value->cover = $gallery->cover;

                                    }
                                }



                                if($k === 'links') {

//                                    $blocks->{$i}->value->{$k} = json_decode($blocks->{$i}->value->{$k});

//                                    dd($block->links);
//                                    $links = json_decode($block->links);
//                                    if(is_string($links)) {
//                                        $links = json_decode($links);
//                                    }
//                                    foreach ($links as $ind => $link) {
//                                        $block->value->image[$ind] = $link->src;
//                                        $block->value->description[$ind] = $link->description;
//                                        $block->value->photograph[$ind] = $link->photograph;
//                                    }

                                }


                            }

                            if($block->blockType === 'mini_note') {
                                if($k === 'image' || $k === 'slider_description') {
                                    $links = json_decode($blockData[$k]);
                                    $blocks->{$i}->value->{$k} = json_decode($block->value->{$k});

                                    foreach ($links as $ind => $link) {
                                        $blocks->{$i}->value->{$k}[$ind] = $link;
                                    }
                                }
                            }
                        }
                    }
                }
            }


            $article['content'] = json_encode($blocks);
            $article['meta'] = json_decode($article['meta']);
        }

        return $article;
    }


    public function updateDraft(array $data) {
        $draft = $this->model::where(['rel_id' => $data['rel_id'], 'status' => $this->model::STATUS_DRAFT])->first();

        if(!$draft) {
            $draft = new $this->model();
            $draft->fill($data);
            $draft->status = $this->model::STATUS_DRAFT;
            $draft->save();
        }
    }

}
