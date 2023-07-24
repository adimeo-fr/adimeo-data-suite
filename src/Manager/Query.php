<?php

namespace App\Manager;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Query
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function removeStopWords($query)
    {
        $keyword = $query['query']['bool']['must'][0]['query_string']['query'];
        $stopwords = json_decode(file_get_contents($this->params->get('data.folder') . DIRECTORY_SEPARATOR . 'stopwords.json'), true);

        foreach ($stopwords as &$word) {
            $word = '/\b' . preg_quote($word, '/') . '\b/';
        }
        $query['query']['bool']['must'][0]['query_string']['query'] = preg_replace($stopwords, '', $keyword);

        return $query;
    }

    public function setPinnedDocuments($query)
    {
        $keyword = $query['query']['bool']['must'][0]['query_string']['query'];
        $pinned = json_decode(file_get_contents($this->params->get('data.folder') . DIRECTORY_SEPARATOR . 'stopwords.json'), true);

        $search = array_search($keyword, array_column($pinned, 'query'));

        if ($search !== false) {
            $query['query']['bool']['should']['pinned']['ids'] = explode(',', $pinned[$search]['ids']);
            $query['query']['bool']['should']['pinned']['organic']['match']['label'] = $keyword;
        }

        return $query;
    }

    public function setSlop($query)
    {
        $keyword = $query['query']['bool']['must'][0]['query_string']['query'];
        $count = count($query['query']['bool']['must']);

        $query['query']['bool']['must'][$count]['span_near']['slop'] = 2;
        $query['query']['bool']['must'][$count]['span_near']['in_order'] = false;

        foreach (explode(' ', $keyword) as $key) {
            if (trim($key) !== '') {
                $query['query']['bool']['must'][$count]['span_near']['clauses'][] = array(
                    'span_multi' => array(
                        'match' => array(
                            'fuzzy' => array(
                                'label' => array(
                                    'value' => $key,
                                    'fuzziness' => 'AUTO'
                                )
                            )
                        )
                    )
                );
            }
        }

        return $query;
    }
}