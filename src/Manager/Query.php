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
        $clean_str = $this->removeAccents(strtolower(str_replace('  ', ' ', $clean_str)));

        if (isset($query['query']['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['query_string']['query'] = $clean_str;
        } elseif (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['must'][0]['query_string']['query'] = $clean_str;
        }

        return $query;
    }

    public function setPinnedDocuments($query, $storeUid)
    {
        $keyword = $this->retrieveKeywordFromQuery($query, true, 'simple_query_string');

        $pinned = json_decode(file_get_contents($this->params->get('data.folder') . DIRECTORY_SEPARATOR . 'pinned.json'), true);

        $search = array_search($keyword, array_column($pinned, 'query'));

        if ($search !== false) {
            $ids = explode(',', $pinned[$search]['ids']);

            $this->addLog('search.log', 'PINNED IDS', print_r(json_encode($ids), true), true);
            $this->addLog('search.log', 'PINNED SEARCH', print_r(json_encode($search), true), true);
            $this->addLog('search.log', 'PINNED KEYWORD', $keyword, true);
            $this->addLog('search.log', 'PINNED STORE ID', $storeUid, true);
            $query['query']['bool']['should']['pinned']['ids'] = array_values(array_filter($ids, function ($id) use ($storeUid) {
                list($ref, $store) = explode('_', $id);
                if ($store == $storeUid) {
                    return $id;
                }
            }));

            $query['query']['bool']['should']['pinned']['organic']['match']['label'] = $keyword;
        }

        return $query;
    }

    public function addFuzziness($query)
    {
        $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['query'] = str_replace(' ', '~1 ', $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['query']) . '~1';

        return $query;
    }

    public function addBoolToQueryString($query)
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0]['query_string'])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string'] = $query['query']['bool']['must'][0]['bool']['must'][0]['query_string'];
            $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string'] = $query['query']['bool']['must'][0]['bool']['must'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['bool']['must']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['default_operator']);
            unset($query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['default_operator']);
        } else if (isset($query['query']['bool']['must'][0])) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string'] = $query['query']['bool']['must'][0]['query_string'];
            $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string'] = $query['query']['bool']['must'][0]['query_string'];
            unset($query['query']['bool']['must'][0]['query_string']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['default_operator']);
            unset($query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['type']);
            unset($query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['default_operator']);
        }

        return $query;
    }

    public function setAnalyzedFields($query)
    {
        // Without fuzinness
        $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['fields'] = [
            'label^100',
            'label.1term^200',
            'label.3term^150',
            'label_term_1^50',
            'label_term_2^50',
            'label_term_3^50',
            'brand_analyzed_french^200'
        ];
        $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['boost'] = 100;

        // With fuzziness
        $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['fields'] = [
            'label^100',
            'label.1term^200',
            'label.3term^150',
            'label_term_1^50',
            'label_term_2^50',
            'label_term_3^50',
            'brand_analyzed_french^200'
        ];

        $keyword = $this->retrieveKeywordFromQuery($query, true, 'simple_query_string');
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $last = array_slice($words, -1)[0];

            $query['query']['bool']['must'][0]['bool']['should'][2]['simple_query_string'] = [
                'query' => strtoupper($last),
                'fields' => [
                    'attr_taille^10'
                ]
            ];
        }

        return $query;
    }

    public function addMinimumShouldMatch($query)
    {
        $keyword = $this->retrieveKeywordFromQuery($query, true, 'simple_query_string');
        if (!str_contains($keyword, 'category_id')) {
            $query['query']['bool']['must'][0]['bool']['should'][0]['simple_query_string']['minimum_should_match'] = '100%';
            $query['query']['bool']['must'][0]['bool']['should'][1]['simple_query_string']['minimum_should_match'] = '100%';
        }

        return $query;
    }

    public function setFunctionScore($query)
    {
        $array = [];
        $array['query']['function_score']['query'] = $query['query'];
        $array['query']['function_score']['functions'][] = [
            'script_score' => [
                'script' => [
                    'source' => '(doc[\'stock\'].value == 0 && doc[\'stock_delivery\'].value == 0) || doc[\'product_store_strategies\'].value == 3 || doc[\'product_store_strategies\'].contains(3) ? 0 : _score'
                ]
            ]
        ];
        $array['query']['function_score']['score_mode'] = 'sum';

        if (isset($query['aggs'])) {
            $array['aggs'] = $query['aggs'];
        }
        if (isset($query['collapse'])) {
            $array['collapse'] = $query['collapse'];
        }
        if (isset($query['sort'])) {
            $array['sort'] = $query['sort'];
        }
        if (isset($query['suggest'])) {
            $array['suggest'] = $query['suggest'];
        }

        return $array;
    }

    public function addLog($filename, $section, $data, $append)
    {
        $file = $this->params->get('log.folder') . '/' . $filename;
        file_put_contents($file, $section . ':' . $data . "\n", $append ? FILE_APPEND : 0);
    }

    private function retrieveKeywordFromQuery($query, $replace = false, $property = 'query_string')
    {
        if (isset($query['query']['bool']['must'][0]['bool']['must'][0][$property])) {
            return $replace ? str_replace(['~1'], '', $query['query']['bool']['must'][0]['bool']['must'][0][$property]['query']) : $query['query']['bool']['must'][0]['bool']['must'][0][$property]['query'];
        } else if (isset($query['query']['bool']['must'][0][$property])) {
            return $replace ? str_replace(['~1'], '', $query['query']['bool']['must'][0][$property]['query']) : $query['query']['bool']['must'][0][$property]['query'];
        } else if (isset($query['query']['bool']['must'][0]['bool']['should'][0][$property])) {
            return $replace ? str_replace(['~1'], '', $query['query']['bool']['must'][0]['bool']['should'][0][$property]['query']) : $query['query']['bool']['must'][0]['bool']['should'][0][$property]['query'];
        }

        return '';
    }

    private function removeAccents($string)
    {
        if (!preg_match('/[\x80-\xff]/', $string))
            return $string;

        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
            chr(195) . chr(135) => 'C', chr(195) . chr(136) => 'E',
            chr(195) . chr(137) => 'E', chr(195) . chr(138) => 'E',
            chr(195) . chr(139) => 'E', chr(195) . chr(140) => 'I',
            chr(195) . chr(141) => 'I', chr(195) . chr(142) => 'I',
            chr(195) . chr(143) => 'I', chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
            chr(195) . chr(159) => 's', chr(195) . chr(160) => 'a',
            chr(195) . chr(161) => 'a', chr(195) . chr(162) => 'a',
            chr(195) . chr(163) => 'a', chr(195) . chr(164) => 'a',
            chr(195) . chr(165) => 'a', chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
            chr(195) . chr(177) => 'n', chr(195) . chr(178) => 'o',
            chr(195) . chr(179) => 'o', chr(195) . chr(180) => 'o',
            chr(195) . chr(181) => 'o', chr(195) . chr(182) => 'o',
            chr(195) . chr(182) => 'o', chr(195) . chr(185) => 'u',
            chr(195) . chr(186) => 'u', chr(195) . chr(187) => 'u',
            chr(195) . chr(188) => 'u', chr(195) . chr(189) => 'y',
            chr(195) . chr(191) => 'y',
            // Decompositions for Latin Extended-A
            chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a',
            chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a',
            chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a',
            chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c',
            chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c',
            chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c',
            chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c',
            chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd',
            chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd',
            chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e',
            chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e',
            chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e',
            chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e',
            chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e',
            chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g',
            chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g',
            chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g',
            chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g',
            chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h',
            chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h',
            chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i',
            chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i',
            chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i',
            chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i',
            chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i',
            chr(196) . chr(178) => 'IJ', chr(196) . chr(179) => 'ij',
            chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j',
            chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k',
            chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L',
            chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L',
            chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L',
            chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L',
            chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L',
            chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N',
            chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N',
            chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N',
            chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N',
            chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N',
            chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o',
            chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o',
            chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o',
            chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe',
            chr(197) . chr(148) => 'R', chr(197) . chr(149) => 'r',
            chr(197) . chr(150) => 'R', chr(197) . chr(151) => 'r',
            chr(197) . chr(152) => 'R', chr(197) . chr(153) => 'r',
            chr(197) . chr(154) => 'S', chr(197) . chr(155) => 's',
            chr(197) . chr(156) => 'S', chr(197) . chr(157) => 's',
            chr(197) . chr(158) => 'S', chr(197) . chr(159) => 's',
            chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
            chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't',
            chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't',
            chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't',
            chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u',
            chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u',
            chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u',
            chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u',
            chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u',
            chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u',
            chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w',
            chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y',
            chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z',
            chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z',
            chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z',
            chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's'
        );

        return strtr($string, $chars);
    }
}
