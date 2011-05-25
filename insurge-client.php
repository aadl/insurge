<?php
/**
 * Locum is a software library that abstracts bibliographic social catalog data
 * and functionality.  It can then be used in a variety of applications to both
 * consume and contribute data from the repository.
 * @package Insurge
 * @author John Blyberg
 */

require_once('insurge.php');
require_once('vendor/MachineTag.php');

/**
 * The insurge client class provides the front-end functionality for accessing
 * and contributing social repository data within the context of the application
 * using this class.
 */
class insurge_client extends insurge {

  /**
   * Submits a bibliographic tags to the database.
   *
   * @param int $uid Unique user ID
   * @param int $bnum Unique identifier for content.
   * @param string $tag_string A raw, unformatted string of tags to be processed.
   */
  public function submit_tags($uid, $bnum, $tag_string, $public = 1, $timestamp = NULL) {
    $namespace = "''";
    $predicate = "''";
    $value = "''";

    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $tag_arr = $this->prepare_tag_string($tag_string);

    if ($timestamp) {
      $tag_date = date('Y-m-d H:i:s', $timestamp);
      $tag_date = "'$tag_date'";
    }
    else {
      $tag_date = 'NOW()';
    }

    $dbq = $db->query('SELECT DISTINCT(tag) FROM insurge_tags WHERE bnum = "' . $bnum . '" AND uid = ' . $uid);
    $existing_tags = $dbq->fetchCol();
    foreach ($tag_arr as $tag) {
      if (!in_array($tag, $existing_tags)){
        $next_tid = $db->nextID('insurge_tags');
        // Check tag if it matches namespace:predicate=value format
        $mtag = new MachineTag($tag);
        if($mtag->is_machinetag()) {
          $namespace = $mtag->namespace();
          $predicate = $mtag->predicate();
          $value = $mtag->value();
          $namespace = $db->quote($namespace, 'text');
          $predicate = $db->quote($predicate, 'text');
          $value = $db->quote($value, 'text');
        }

        $tag = $db->quote($tag, 'text');
        if ($group_id) {
          $repos_id = $group_id . '-' . $next_tid;
        }
        $sql = "INSERT INTO insurge_tags VALUES ($next_tid, NULL, NULL, $uid, '$bnum', $tag, $namespace, $predicate, $value, $tag_date, $public)";
        $res =& $db->exec($sql);
      }
    }

  }

  /**
   * Grabs an array of tags and their totals (weights).
   *
   * @param int $uid Unique user ID
   * @param array $bnum_arr Optional array of unique content ids to scope tag retrieval on.
   * @param string $limit Limit the number of results returned.
   */
  public function get_tag_totals($uid = NULL, $bnum_arr = NULL, $tag_name = NULL, $rand = TRUE, $limit = 500, $offset = 0, $order = 'ORDER BY count DESC', $public = 1) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $where_prefix = 'WHERE';
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if ($tag_name) { $where_str .= ' ' . $where_prefix . ' tag = ' . $db->quote($tag_name, 'text'); $where_prefix = 'AND'; }
    if (count($bnum_arr)) { $where_str .= ' ' . $where_prefix . ' bnum IN ("' . implode('", "', $bnum_arr) . '") '; $where_prefix = 'AND'; }
    $where_str .= ' ' . $where_prefix . ' public = ' . $public;
    $sql = 'SELECT tag, count(tag) AS count FROM insurge_tags ' . $where_str . ' GROUP BY tag ' . $order;
    if ($limit) { $sql .= " LIMIT $limit"; }
    if ($offset) { $sql .= " OFFSET $offset"; }
    $result =& $db->query($sql);
    $tag_result = $result->fetchAll(MDB2_FETCHMODE_ASSOC);
    if ($rand) { $this->shuffle_with_keys(&$tag_result); }
    return $tag_result;
  }

  public function get_tagged_items($uid = NULL, $tag_name = NULL, $limit = 500, $offset = 0) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $where_prefix = 'WHERE';
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if ($tag_name) { $where_str .= ' ' . $where_prefix . ' tag = ' . $db->quote($tag_name, 'text'); $where_prefix = 'AND'; }

    $sql = 'SELECT count(*) FROM insurge_tags ' . $where_str;
    $dbq = $db->query($sql);
    $tag_result['total'] = $dbq->fetchOne();

    $sql = 'SELECT bnum FROM insurge_tags ' . $where_str;
    if ($limit) { $sql .= " LIMIT $limit"; }
    if ($offset) { $sql .= " OFFSET $offset"; }
    $result =& $db->query($sql);
    $tag_result['bnums'] = $result->fetchCol();
    return $tag_result;
  }

  /**
   * Takes a string of raw tag input and parses it into an array, ready to be processed
   * into the database.
   *
   * @param string $raw_tag_string Raw tag input from an application.
   * @return array Array of tags, processed accoring to our rules.
   */
  public function prepare_tag_string($raw_tag_string) {
    $arTags = array();
    $cPhraseQuote = NULL;
    $sPhrase = NULL;

    // Define some constants
    static $sTokens = " \r\n\t";  // Space, Return, Newline, Tab
    static $sQuotes = "'\"";    // Single and Double Quotes

    do {
      $sToken = isset($sToken)? strtok($sTokens) : strtok($raw_tag_string, $sTokens);

      if ($sToken === FALSE) {
        $cPhraseQuote = NULL;
      } else {
        if ($cPhraseQuote !== NULL) {
          if (substr($sToken, -1, 1) === $cPhraseQuote) {
            if (strlen($sToken) > 1) $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : NULL) . substr($sToken, 0, -1);
            $cPhraseQuote = NULL;
          } else {
            $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : NULL) . $sToken;
          }
        } else {
          if (strpos($sQuotes, $sToken[0]) !== FALSE) {
            if ((strlen($sToken) > 1) && ($sToken[0] === substr($sToken, -1, 1))) {
              $sPhrase = substr($sToken, 1, -1);
            } else {
              $sPhrase = substr($sToken, 1);
              $cPhraseQuote = $sToken[0];
            }
          } else {
            $sPhrase = $sToken;
          }
        }
      }

      if (($cPhraseQuote === NULL) && ($sPhrase != NULL)) {
        $sPhrase = strtolower($sPhrase);
        if (!in_array($sPhrase, $arTags)) $arTags[] = preg_replace('/,/s', '', $sPhrase); {
          $sPhrase = NULL;
        }
      }
    }
    while ($sToken !== FALSE);
    return $arTags;
  }

  /**
   * Updates a tag to something else.
   */
  public function update_tag($oldtag, $newtag, $uid = NULL, $tid = NULL, $bnum = NULL) {
    if ($oldtag != $newtag) {
      $db =& MDB2::connect($this->dsn);
      $where_prefix = 'AND';
      if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; }
      if ($tid) { $where_str .= ' ' . $where_prefix . ' tid = ' . $tid . ' '; }
      if ($bnum) { $where_str .= ' ' . $where_prefix . ' bnum = "' . $bnum . '" '; }
      $tag = $db->quote($newtag, 'text');
      $oldtag = $db->quote($oldtag, 'text');
      $sql = "UPDATE insurge_tags SET tag = $tag WHERE tag = $oldtag " . $where_str;
      $db->exec($sql);
    }
  }

  function delete_user_tag($uid, $tag, $bnum = NULL) {
    if ($uid && $tag) {
      $group_id = $this->insurge_config['repository_info']['group_id'];
      $db =& MDB2::connect($this->dsn);
      $tag = $db->quote($tag);
      $sql = "DELETE FROM insurge_tags WHERE uid = $uid AND tag = $tag";
      if ($bnum) {
        $sql .= " AND bnum = '$bnum'";
      }
      if ($group_id) {
        $sql .= " AND group_id = '$group_id'";
      }
      $db->exec($sql);
    }
  }

  /**
   * Submits a bibliographic rating to the database.
   *
   * @param int $uid Unique user ID
   * @param array $bnum_arr Optional array of bib numbers to scope tag retrieval on.
   * @param int $value The submitted rating.
   */
  function submit_rating($uid, $bnum, $value) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($group_id) {
      $repos_id = $group_id . '-' . $next_tid;
    }
    $sql = 'SELECT COUNT(rate_id) FROM insurge_ratings WHERE bnum = "' . $bnum . '" AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"';
    $dbq =& $db->query('SELECT COUNT(rate_id) FROM insurge_ratings WHERE bnum = "' . $bnum . '" AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"');
    $is_update = $dbq->fetchOne();
    if ($is_update > 1) {
      $db->query('DELETE FROM insurge_ratings WHERE bnum = "' . $bnum . '" AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"');
      $is_update = FALSE;
    }
    if ($is_update) {
      $sql = 'UPDATE insurge_ratings SET rating = ' . $value . ' WHERE bnum = "' . $bnum . '" AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"';
    } else {
      $next_rid = $db->nextID('insurge_ratings');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_rid;
      }
      $sql = "INSERT INTO insurge_ratings VALUES ($next_rid, '$repos_id', '$group_id', $uid, '$bnum', $value, NOW())";
    }
    $res =& $db->exec($sql);
  }

  /**
   * Returns the average value and ratings count of a bib's rating.
   *
   * @param int $bnum Bib number
   * @param boolean $local_only If set to TRUE, this function will return results for your institution only.
   * @return array ratings count and average value if there are ratings.
   */
  function get_rating($bnum, $local_only = FALSE) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $sql = 'SELECT AVG(rating) AS rating, COUNT(rate_id) AS rate_count FROM insurge_ratings WHERE bnum = "' . $bnum .'"';
    if ($local_only) {
      $sql .= ' AND group_id = "' . $group_id . '"';
    }
    $dbq =& $db->query($sql);
    $avg_rating = $dbq->fetchRow(MDB2_FETCHMODE_ASSOC);
    $rating['count'] = $avg_rating['rate_count'];

    if($avg_rating['rating'] >= ($half = ($ceil = ceil($avg_rating['rating']))- 0.5) + 0.25) {
      $rating['value'] = $ceil;
    } else if ($avg_rating['rating'] < $half - 0.25) {
      $rating['value'] = floor($avg_rating['rating']);
    } else {
      $rating['value'] = $half;
    }
    return $rating;
  }

  function get_rating_list($uid = NULL, $bnum = NULL, $limit = 20, $offset = 0, $order = 'ORDER BY rating DESC') {
    $db =& MDB2::connect($this->dsn);
    $offset = $offset ? $offset : 0;
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($bnum) { $where_str .= ' ' . $where_prefix . ' bnum = "' . $bnum . '" '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    $sql = 'SELECT count(*) FROM insurge_ratings WHERE ' . $where_str;
    $dbq = $db->query($sql);
    $ratings_arr['total'] = $dbq->fetchOne();
    $sql = 'SELECT rating, rate_id, bnum, UNIX_TIMESTAMP(rate_date) AS rate_date FROM insurge_ratings WHERE ' . $where_str . ' ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $dbq =& $db->query($sql);
    $ratings_arr['ratings'] = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    return $ratings_arr;
  }

  /**
   * Submits a review for insertion into the database.
   *
   * @param string|int $uid user ID
   * @param int $bnum Bib num
   * @param string $rev_title Title of the review
   * @param string $rev_body The review text
   */
  function submit_review($uid, $bnum, $rev_title, $rev_body) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid && $bnum && $rev_title && $rev_body) {
      $db =& MDB2::connect($this->dsn);
      $next_rid = $db->nextID('insurge_reviews');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_rid;
      }
      $title_ready = $db->quote($rev_title, 'text');
      $rev_body = strip_tags($rev_body, '<b><i><u><strong>');
      $body_ready = $db->quote($rev_body, 'text');
      $sql = "INSERT INTO insurge_reviews VALUES ($next_rid, '$repos_id', '$group_id', '$uid', '$bnum', $title_ready, $body_ready, NOW(), NOW())";
      $db->exec($sql);
    }
  }

  /**
   * Does review retrieval from the database.
   *
   * @param string|int $uid user ID
   * @param array $bnum_arr Array of bib nums to match
   * @param array $rev_id_arr Array of review ID to match
   * @param int $limit Result limiter
   * @param int $offset Result offset for purposes of paging
   */
  function get_reviews($uid = NULL, $bnum_arr = NULL, $rev_id_arr = NULL, $limit = 10, $offset = 0, $order = 'ORDER BY rev_create_date DESC') {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if (count($bnum_arr)) { $where_str .= ' ' . $where_prefix . ' bnum IN ("' . implode('", "', $bnum_arr) . '") '; $where_prefix = 'AND'; }
    if (count($rev_id_arr)) { $where_str .= ' ' . $where_prefix . ' rev_id IN (' . implode(', ', $rev_id_arr) . ') '; $where_prefix = 'AND'; }

    $sql = 'SELECT count(*) FROM insurge_reviews WHERE ' . $where_str;
    $dbq = $db->query($sql);
    if (!PEAR::isError($dbq)) {
      $reviews['total'] = $dbq->fetchOne();
    }
    if($where_str == '') { $where_str = "1"; }
    $sql = 'SELECT rev_id, group_id, uid, bnum, rev_title, rev_body, UNIX_TIMESTAMP(rev_last_update) AS rev_last_update, UNIX_TIMESTAMP(rev_create_date) AS rev_create_date FROM insurge_reviews WHERE ' . $where_str . ' ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $dbq = $db->query($sql);
    if (!PEAR::isError($dbq)) {
      $reviews['reviews'] = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    }

    return $reviews;
  }

  function update_review($uid, $rev_id, $rev_title, $rev_body) {
    $db =& MDB2::connect($this->dsn);
    if ($rev_id) {
      $rev_title = $db->quote($rev_title, 'text');
      $rev_body = $db->quote($rev_body, 'text');
      if ($uid) { $where_str = ' AND uid = ' . $uid; }
      $sql = "UPDATE insurge_reviews SET rev_title = $rev_title, rev_body = $rev_body WHERE rev_id = $rev_id" . $where_str;
      $db->exec($sql);
    }
  }

  function delete_review($uid, $rev_id) {
    $db =& MDB2::connect($this->dsn);
    if ($uid && $rev_id) {
      if ($uid) { $where_str = ' AND uid = ' . $uid; }
      $sql = "DELETE FROM insurge_reviews WHERE rev_id = $rev_id" . $where_str;
      $db->exec($sql);
    }
  }

  /**
   * Checks to see if a $bnum has already been reviewed by $uid
   *
   * @param string|int $uid user ID
   * @param int $bnum Bib num
   * @return int Number of reviews that users has written for $bnum
   */
  function check_reviewed($uid, $bnum) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $dbq = $db->query("SELECT COUNT(*) FROM insurge_reviews WHERE group_id = '$group_id' AND bnum = '$bnum' AND uid = '$uid'");
    return $dbq->fetchOne();
  }

  function add_checkout_history($uid, $bnum, $co_date, $title, $author) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid && $bnum && $co_date) {
      $db =& MDB2::connect($this->dsn);
      $next_hist_id = $db->nextID('insurge_reviews');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_hist_id;
      }
      $title_txt = $db->quote($rev_body, 'text');
      $author_txt = $db->quote($rev_body, 'text');
      $sql = "INSERT INTO insurge_history VALUES ($next_hist_id, '$repos_id', '$group_id', $uid, '$bnum', '$co_date', $title_txt, $author_txt)";
      $db->exec($sql);
    }
  }

  function get_checkout_history($uid = NULL, $limit = NULL, $offset = NULL) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }

    $sql = "SELECT * FROM insurge_history WHERE " . $where_str . $where_prefix . " ORDER BY codate DESC, hist_id DESC";
    $dbq = $db->query($sql);
    $result = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    return $result;
  }

  function get_list_items($list_id = 0, $field = 'value', $sort = 'ASC', $search_term = '') {
    if (empty($field)) {
      $field = 'value';
    }
    if (empty($sort)) {
      $sort = 'ASC';
    }
    // lists are stored in Machine Tags with the format "list#:place=X", where # is the list ID, and place is the item's position in the list
    if ($list_id = intval($list_id)) {
      $db =& MDB2::connect($this->dsn);
      $namespace = "list$list_id";
      if ($field == 'value') {
        $field = '(value+0)';
      }
      if ($search_term) {
        $search_term = "'%". $db->escape($search_term) . "%'";
        $search_sql = "AND (title LIKE $search_term " .
                      "OR author LIKE $search_term " .
                      "OR callnum LIKE $search_term " .
                      "OR notes LIKE $search_term " .
                      "OR subjects LIKE $search_term) ";
      }
      $sql = "SELECT * FROM insurge_tags, locum_bib_items " .
             "WHERE namespace = '$namespace' " .
             "AND insurge_tags.bnum = locum_bib_items.bnum " .
             $search_sql .
             "ORDER BY $field $sort, (value+0) $sort";
      $dbq = $db->query($sql);
      $result = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    }
    else {
      $result = FALSE;
    }
    return $result;
  }

  function get_item_list_ids($bnum) {
    $list_ids = array();

    $db =& MDB2::connect($this->dsn);
    $dbq = $db->query("SELECT * FROM insurge_tags WHERE namespace LIKE 'list%' and bnum = $bnum ORDER BY tag_date DESC");
    while ($tag = $dbq->fetchRow(MDB2_FETCHMODE_ASSOC)) {
      $list_id = str_replace('list', '', $tag['namespace']);
      $list_ids[] = $list_id;
    }

    return $list_ids;
  }

  function add_list_item($uid, $list_id, $bnum, $timestamp = NULL) {
    $db =& MDB2::connect($this->dsn);
    $namespace = 'list' . $list_id;
    // Check if item is already on list
    $dbq = $db->query("SELECT tid FROM insurge_tags WHERE namespace = '$namespace' AND bnum = $bnum");
    if ($dbq->numRows()) {
      return FALSE;
    }
    $dbq = $db->query("SELECT MAX(value+0) FROM insurge_tags WHERE namespace = '$namespace' AND predicate = 'place'");
    $max = $dbq->fetchOne();
    $place = $max + 1;
    $tag = "$namespace:place=$place";
    self::submit_tags($uid, $bnum, $tag, 0, $timestamp);
    return TRUE;
  }

  function move_list_item($list_id, $cur_pos, $new_pos) {
    $db =& MDB2::connect($this->dsn);
    $namespace = 'list' . $list_id;

    // Make sure $new_pos is within the current list limits
    $dbq = $db->query("SELECT MIN(value+0) AS min, MAX(value+0) AS max FROM insurge_tags WHERE namespace='$namespace'");
    $limits = $dbq->fetchRow(MDB2_FETCHMODE_ASSOC);
    if ($new_pos < $limits['min']) {
      $new_pos = $limits['min'];
    }
    else {
      if ($new_pos > $limits['max']) {
        $new_pos = $limits['max'];
      }
    }

    // Move item to temp position
    $db->query("UPDATE insurge_tags SET value = 'temp' WHERE namespace = '$namespace' AND predicate = 'place' AND value = '$cur_pos'");
    // Adjust items in between
    if ($cur_pos > $new_pos) {
      // Moving item to lower spot in the list, move everything else up one spot
      $db->query("UPDATE insurge_tags SET value = value+1 WHERE namespace = '$namespace' AND predicate = 'place' AND value+0 >= $new_pos AND value+0 < $cur_pos");
    }
    else if ($cur_pos < $new_pos) {
      // Moving item to higher spot in the list, move everything else down one spot
      $db->query("UPDATE insurge_tags SET value = value-1 WHERE namespace = '$namespace' AND predicate = 'place' AND value+0 <= $new_pos AND value+0 > $cur_pos");
    }
    // Move item from temp position to new position
    $db->query("UPDATE insurge_tags SET value = '$new_pos' WHERE namespace = '$namespace' AND predicate = 'place' AND value = 'temp'");
    // Update tag text based on values
    $min = min($cur_pos, $new_pos);
    $max = max($cur_pos, $new_pos);
    if ($min > 0 && $max > 0) {
      $db->query("UPDATE insurge_tags SET tag = CONCAT(namespace, ':', predicate, '=', value) WHERE namespace = '$namespace' AND value+0 BETWEEN $min AND $max");
    }
  }

  // Reorder List based on array with keys corresponding to bnums values corresponding to new places
  function reorder_list($list_id, $new_order = array()) {
    $db =& MDB2::connect($this->dsn);
    $namespace = 'list' . $list_id;
    if (count($new_order)) {
      foreach($new_order as $bnum => $new_pos) {
        $new_tag = "$namespace:place=$new_pos";
        $db->query("UPDATE insurge_tags SET tag = '$new_tag', value = '$new_pos' WHERE namespace = '$namespace' AND predicate = 'place' AND bnum = $bnum");
      }
    }
  }

  function delete_list_item($list_id, $place) {
    $db =& MDB2::connect($this->dsn);
    $namespace = 'list' . $list_id;

    // Remove tag at the given place
    $db->query("DELETE FROM insurge_tags WHERE namespace = '$namespace' AND predicate = 'place' AND value = '$place'");
    // Move items in higher spot down by one
    $db->query("UPDATE insurge_tags SET value = value-1 WHERE namespace = '$namespace' AND predicate = 'place' AND value > $place");
  }

  /**
   * Takes a reference to an array and shuffles it, preserving keys.
   *
   * @param array Reference to the array in question.
   */
  function shuffle_with_keys(&$array) {
    $aux = array();
    $keys = array_keys($array);
    shuffle($keys);
    foreach($keys as $key) {
      $aux[$key] = $array[$key];
      unset($array[$key]);
    }
    $array = $aux;
  }

}
