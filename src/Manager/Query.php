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
        $query['query']['bool']['must'][0]['bool']['should'][0]['query_string']['query'] = str_replace(' ', '~ AND ', $query['query']['bool']['must'][0]['bool']['should'][0]['query_string']['query']) . '~';
        $query['query']['bool']['must'][0]['bool']['should'][1]['query_string']['query'] = str_replace(' ', '~ OR ', $query['query']['bool']['must'][0]['bool']['should'][1]['query_string']['query']) . '~';

        return $query;
    }

    public function addBoolToQueryString($query)
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['query_string'] = $query['query']['bool']['must'][0]['bool']['must'][0]['query_string'];
            $query['query']['bool']['must'][0]['bool']['should'][1]['query_string'] = $query['query']['bool']['must'][0]['bool']['should'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['bool']['must']);
        } else if (isset($query['query']['bool']['must'][0])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['query_string'] = $query['query']['bool']['must'][0]['query_string'];
            $query['query']['bool']['must'][0]['bool']['should'][1]['query_string'] = $query['query']['bool']['must'][0]['bool']['should'][0]['query_string'];;
            unset($query['query']['bool']['must'][0]['query_string']);
        }

        return $query;
    }

    public function setAnalyzedFields($query)
    {
        $query['query']['bool']['must'][0]['bool']['should'][0]['query_string']['fields'] = [
            'name^10',
            'label_term_1^8',
            'label_term_2^6',
            'label_term_3^4'
        ];
        $query['query']['bool']['must'][0]['bool']['should'][1]['query_string']['fields'] = [
            'name^10',
            'label_term_1^8',
            'label_term_2^6',
            'label_term_3^4'
        ];

        $keyword = $this->retrieveKeywordFromQuery($query, true);
        $last = array_slice(explode(' ', $keyword), -1)[0];

        $query['query']['bool']['must'][0]['bool']['should'][2]['query_string'] = [
            'query' => strtoupper($last),
            'fields' => [
                'attr_taille^10'
            ]
        ];

        return $query;
    }

    private function retrieveKeywordFromQuery($query, $replace = false)
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            return $replace ? str_replace(['~ AND', '~'], '', $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query']) : $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'];
        } else if (isset($query['query']['bool']['must'][0]['query_string'])) {
            return $replace ? str_replace(['~ AND', '~'], '', $query['query']['bool']['must'][0]['query_string']['query']) : $query['query']['bool']['must'][0]['query_string']['query'];
        } else if (isset($query['query']['bool']['must'][0]['bool']['should'][0]['query_string'])) {
            return $replace ? str_replace(['~ AND', '~'], '', $query['query']['bool']['must'][0]['bool']['should'][0]['query_string']['query']) : $query['query']['bool']['must'][0]['bool']['should'][0]['query_string']['query'];
        }

        return '';
    }
}