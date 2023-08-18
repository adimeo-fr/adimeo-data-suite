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
        $clean_str = str_replace('  ', ' ', $clean_str);

        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['query_string']['query'] = $clean_str;
        } elseif (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'] = $clean_str;
        }

        return $query;
    }

    public function setPinnedDocuments($query)
    {
        $keyword = $this->retrieveKeywordFromQuery($query, true, 'simple_query_string');

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
        $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['query'] = str_replace(' ', '~ ', $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['query']) . '~';

        return $query;
    }

    public function addBoolToQueryString($query)
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string'] = $query['query']['bool']['must'][0]['bool']['must'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['bool']['must']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['default_operator']);
        } else if (isset($query['query']['bool']['must'][0])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string'] = $query['query']['bool']['must'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['query_string']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['default_operator']);
        }

        return $query;
    }

    public function setAnalyzedFields($query)
    {
        $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['fields'] = [
            'label^100',
            'label_term_1^50',
            'label_term_2^50',
            'label_term_3^50',
            'brand^10'
        ];
        $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['fields'] = [
            'label^100',
            'label_term_1^50',
            'label_term_2^50',
            'label_term_3^50',
            'brand^10'
        ];

        $keyword = $this->retrieveKeywordFromQuery($query, true, 'simple_query_string');
        $last = array_slice(explode(' ', $keyword), -1)[0];

        $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string'] = [
            'query' => strtoupper($last),
            'fields' => [
                'attr_taille^10'
            ]
        ];

        return $query;
    }

    private function retrieveKeywordFromQuery($query, $replace = false, $property = 'query_string')
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0][$property])) {
            return $replace ? str_replace(['~'], '', $query['query']['bool']['must'][0]['bool']['must'][0][$property]['query']) : $query['query']['bool']['must'][0]['bool']['must'][0][$property]['query'];
        } else if (isset($query['query']['bool']['must'][0][$property])) {
            return $replace ? str_replace(['~'], '', $query['query']['bool']['must'][0][$property]['query']) : $query['query']['bool']['must'][0][$property]['query'];
        } else if (isset($query['query']['bool']['must'][0]['bool']['should'][0][$property])) {
            return $replace ? str_replace(['~'], '', $query['query']['bool']['must'][0]['bool']['should'][0][$property]['query']) : $query['query']['bool']['must'][0]['bool']['should'][0][$property]['query'];
        }

        return '';
    }
}