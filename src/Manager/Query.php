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
        $keyword = $this->retrieveKeywordFromQuery($query);

        $stopwords = json_decode(file_get_contents($this->params->get('data.folder') . DIRECTORY_SEPARATOR . 'stopwords.json'), true);

        foreach ($stopwords as &$word) {
            $word = '/\b' . preg_quote($word, '/') . '\b/';
        }

        $clean_str = trim(preg_replace($stopwords, '', $keyword));

        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['query_string']['query'] = $clean_str;
        } elseif (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'] = $clean_str;
        }

        return $query;
    }

    public function setPinnedDocuments($query)
    {
        $keyword = $this->retrieveKeywordFromQuery($query, true);

        $pinned = json_decode(file_get_contents($this->params->get('data.folder') . DIRECTORY_SEPARATOR . 'pinned.json'), true);

        $search = array_search($keyword, array_column($pinned, 'query'));

        if ($search !== false) {
            $query['query']['bool']['should']['pinned']['ids'] = explode(',', $pinned[$search]['ids']);
            $query['query']['bool']['should']['pinned']['organic']['match']['label'] = $keyword;
        }

        return $query;
    }

    public function addFuzziness($query)
    {
        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['query_string']['fuzziness'] = 'AUTO:10,20';
            $query['query']['bool']['must'][0]['query_string']['default_operator'] = 'OR';
            $query['query']['bool']['must'][0]['query_string']['query'] = str_replace(' ', '~AUTO ', $query['query']['bool']['must'][0]['query_string']['query']) . '~AUTO';
        } elseif (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['fuzziness'] = 'AUTO:10,20';
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['default_operator'] = 'OR';
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'] = str_replace(' ', '~AUTO ', $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query']) . '~AUTO';
        }

        return $query;
    }

    public function addBoolToQueryString($query)
    {
        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string'] = $query['query']['bool']['must'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['query_string']);
        }

        return $query;
    }

    public function setSlop($query)
    {
        $keyword = $this->retrieveKeywordFromQuery($query);

        $query['query']['bool']['must'][0]['bool']['should'][0]['span_near']['slop'] = 2;
        $query['query']['bool']['must'][0]['bool']['should'][0]['span_near']['in_order'] = false;

        foreach (explode(' ', $keyword) as $key) {
            if (trim($key) !== '') {
                $key = rtrim($key, '~');
                $query['query']['bool']['must'][0]['bool']['should'][0]['span_near']['clauses'][] = array(
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

    private function retrieveKeywordFromQuery($query, $replace = false)
    {
        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            return $replace ? str_replace('~AUTO', '', $query['query']['bool']['must'][0]['query_string']['query']) : $query['query']['bool']['must'][0]['query_string']['query'];
        } elseif (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            return $replace ? str_replace('~AUTO', '', $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query']) : $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'];
        }

        return '';
    }
}