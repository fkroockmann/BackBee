<?php

namespace BackBuilder\Services\Local;

use BackBuilder\Services\Auth\Auth,
    BackBuilder\Services\Local\AbstractServiceLocal,
    BackBuilder\Util\String;

class Keyword extends AbstractServiceLocal {

    /**
     * @exposed(secured=true)
     * 
     */
    public function getKeywordTree($root_uid) {
        $em = $this->bbapp->getEntityManager();
        $tree = array();
        if ($root_uid && $root_uid !== null) {
            $keywordFolder = $em->find('\BackBuilder\NestedNode\KeyWord', $root_uid);
            if ($keywordFolder) {
                foreach ($em->getRepository('\BackBuilder\NestedNode\KeyWord')->getDescendants($keywordFolder, 1) as $child) {
                    $leaf = new \stdClass();
                    $leaf->attr = new \stdClass();
                    $leaf->attr->rel = 'folder';
                    $leaf->attr->id = 'node_' . $child->getUid();
                    $leaf->attr->state = 1;
                    $leaf->data = $child->getKeyWord();
                    $leaf->state = 'closed';

                    $children = $this->getKeywordTree($child->getUid());
                    $leaf->state = ((count($children) > 0) ? 'closed' : 'leaf');

                    $tree[] = $leaf;
                }
            }
        } else {
            $keyword = $em->getRepository('\BackBuilder\NestedNode\KeyWord')->getRoot();
            if ($keyword) {
                $leaf = new \stdClass();
                $leaf->attr = new \stdClass();
                $leaf->attr->rel = 'root';
                $leaf->attr->id = 'node_' . $keyword->getUid();
                $leaf->attr->state = 1;
                $leaf->data = $keyword->getKeyWord();
                $leaf->state = 'closed';


                $children = $this->getKeywordTree($keyword->getUid());
                $leaf->state = ((count($children) > 0) ? 'closed' : 'leaf');

                $tree[] = $leaf;
            }
        }

        return $tree;
    }

    /**
     * @exposed : true
     * @secured : true
     */
    public function postKeywordForm($keywordInfos) {
        $keywordInfos = (object) $keywordInfos;
        $mode = $keywordInfos->mode;
        $em = $this->bbapp->getEntityManager();
        $leaf = false;
        if ($mode == "create") {
            $keyword = new \BackBuilder\NestedNode\KeyWord();
            $keyword->setKeyWord($keywordInfos->keyword);
            $parent = $em->find('\BackBuilder\NestedNode\KeyWord', $keywordInfos->parentUid);
            #$keyword->setParent($parent)
            /* add as first of parent child */
            if ($parent) {
                $keyword = $em->getRepository('\BackBuilder\NestedNode\KeyWord')->insertNodeAsFirstChildOf($keyword, $parent);
            }
        } else {
            $keyword = $em->find("\BackBuilder\NestedNode\Keyword", $keywordInfos->keywordUid);
            $keyword->setKeyWord($keywordInfos->keyword);
        }
        $em->persist($keyword);
        $em->flush();

        if ($keyword) {
            $leaf = new \stdClass();
            $leaf->attr = new \stdClass();
            $leaf->attr->rel = 'leaf';
            $leaf->attr->id = 'node_' . $keyword->getUid();
            $leaf->data = html_entity_decode($keyword->getKeyWord(), ENT_COMPAT, 'UTF-8');
            return $leaf;
        }
        return $leaf;
    }

    /**
     * @exposed : true
     * @secured : true
     */
    public function deleteKeyword($keywordId) {
        if (!isset($keywordId))
            throw new ServicesException(sprintf("Keyword can't be null"));
        $result = false;
        $em = $this->bbapp->getEntityManager();
        $keyword = $em->find('\BackBuilder\NestedNode\KeyWord', $keywordId);
        if (!is_null($keyword)) {
            $em->remove($keyword);
            $em->flush();
            $result = true;
        }
        return $result;
    }

    /**
     * @exposed : true
     * @secured : true
     */
    public function getKeywordsList() {
        $em = $this->bbapp->getEntityManager();
        $root = $em->getRepository('\BackBuilder\NestedNode\KeyWord')->getRoot();
        $keywordList = $em->getRepository("\BackBuilder\NestedNode\KeyWord")->getDescendants($root);
        $keywordContainer = array();
        if (!is_null($keywordList)) {
            foreach ($keywordList as $keyword) {
                $suggestion = new \stdClass();
                $suggestion->label = $keyword->getKeyWord();
                $suggestion->value = $keyword->getUid();
                $keywordContainer[] = $suggestion;
            }
            /* save cache here */
        }
        return $keywordContainer;
    }

    public function getKeywordByIds() {
        $em = $this->bbapp->getEntityManager();
    }

}

?>