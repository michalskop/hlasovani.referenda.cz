<?php
/**
common part for vote-event
*/



$vote_events = json_decode(file_get_contents($path . "json/vote-events.json"));
$option_meaning = json_decode(file_get_contents($path . "json/option-meaning.json"));
$people = json_decode(file_get_contents($path . "json/people.json"));
$partiesjson = json_decode(file_get_contents($path . "json/parties.json"));

if (!isset($_GET['identifier']) or $_GET['identifier'] == '') {
    $smarty->assign('error_message',$text['vote-event_no_identifier_message']);
    $smarty->display('error.tpl');
    die();
}
    
$ve_id = trim($_GET['identifier']);

$parties = [];
$data = [];

$group2party = new stdClass();
foreach ($partiesjson as $pkey => $party) {
  foreach ($party->children as $ckey => $child) {
    $group2party->$child = $pkey;
  }
}

if (!isset($vote_events->$ve_id) or !isset($vote_events->$ve_id->votes) or count($vote_events->$ve_id->votes) == 0) {
    $error = [
      'error' => true,
      'description' => 'vote-event_unknown_identifier_warning'
    ];
    $smarty->assign('title',$text[$error['description']]);
    $smarty->assign('error',$error);
    return;
}

foreach($vote_events->$ve_id->votes as $vkey => $v) {
  $voter_id = $v->voter_id;
  $group_id = $v->group_id;
  $party_id = $group2party->$group_id;
  if (!isset($parties[$party_id])) {
    $parties[$party_id] = $partiesjson->$party_id;
    $parties[$party_id]->people = [];
    $parties[$party_id]->link = $path . 'party.php?party=' . $party_id . $term_chunk;
  }
  $p = new stdClass();
  $p->link = $path . 'person.php?identifier='.person_id2identifier($voter_id,$people) . $term_chunk;
  $p->name = $people->$voter_id->name;
  $p->family_name = $people->$voter_id->family_name;
  $p->given_name = $people->$voter_id->given_name;
  //print_r($v);die();
  $p->single_match = single_match($v->option,$issue->vote_events->$ve_id->pro_issue,$option_meaning,$vote_events->$ve_id->motion->requirement);
  $p->option = $text['vote_options'][$v->option];
  $p->background = single_match2color($p->single_match);
  $p->opacity = single_match2opacity($p->single_match);
  $parties[$party_id]->people[] = $p;
  $data[] = $p;
}

//sort people by single match
usort($data, function($a, $b) {
  return $a->single_match - $b->single_match;
});

// sort parties by position
usort($parties, function($a, $b) {
  return $a->position - $b->position;
});

foreach ($parties as $key=>$party) {
  usort($parties[$key]->people, function($a, $b) {
    return $a->single_match - $b->single_match;
  });
}

//parties are not good enough (for chart), let's make virtual parties
$virtualparties = [];

foreach($parties as $party) {
  foreach ([-1,0,1] as $sm) {
    $nparty = clone $party;
    unset($nparty->people);
    $nparty->single_match = $sm;
    $virtualparties[] = $nparty;
  }
}

foreach ($parties as $key => $party) {
  $virtualparties[3*$key]->people = [];
  $virtualparties[3*$key+1]->people = [];
  $virtualparties[3*$key+2]->people = [];
  foreach ($party->people as $person) {
    $virtualparties[3*$key+1+$person->single_match]->people[] = $person;
  }
}

usort($virtualparties, function($a, $b) {
  return ($a->single_match - $b->single_match)*1000 + $a->position - $b->position;
});

usort($parties, function($a, $b) {
  return (count($b->people) - count($a->people));
});

foreach($parties as $key => $party) {
  usort($parties[$key]->people, function($a, $b) {
    return (strcoll($a->family_name,$b->family_name));
  });
}

//vote event
$vote_event = (object)array_merge((array)$issue->vote_events->$ve_id, (array)$vote_events->$ve_id);

//arcs
$arcs = create_arcs($data);

//score
$score = calculate_score($data);

$smarty->assign('title',$issue->vote_events->$ve_id->name);
$smarty->assign('vote_event',$vote_event);
$smarty->assign('issue',$issue);
$smarty->assign('virtualparties',json_encode($virtualparties));
$smarty->assign('arcs',json_encode($arcs));
$smarty->assign('score',json_encode($score));
$smarty->assign('parties',$parties);


function create_arcs($data) {
    $single_match2option_meaning = [
        1 => 'for',
        0 => 'neutral',
        -1 => 'against'
    ];
    $limits = [
        'for'=>['lo'=>null,'hi'=>null,'color'=>'green'],
        'against'=>['lo'=>null,'hi'=>null,'color'=>'darkred'],
    ];
    foreach($data as $key=>$row) {
        if (isset($limits[$single_match2option_meaning[$row->single_match]])){
            if (is_null($limits[$single_match2option_meaning[$row->single_match]]['lo']))
                $limits[$single_match2option_meaning[$row->single_match]]['lo'] = $key;
            $limits[$single_match2option_meaning[$row->single_match]]['hi'] = $key;
        }
    }
    $arcs = [];
    foreach ($limits as $limit) {
        if (!is_null($limit['lo'])) {
            $a = new StdClass();
            $a->start = $limit['lo'];
            $a->end = $limit['hi'];
            $a->color = $limit['color'];
            $a->opacity = 0.15;
            $arcs[] = $a;
        }
    }
    return $arcs;
}

function calculate_score($data) {
    $single_match2option_meaning = [
        1 => 'for',
        0 => 'neutral',
        -1 => 'against'
    ];
    $score = ['for'=>['value'=>0,'color'=>'green'],'neutral'=>['value'=>0,'color'=>'gray'],'against'=>['value'=>0,'color'=>'darkred'],];
    foreach ($data as $row) {
        $score[$single_match2option_meaning[$row->single_match]]['value']++;
    }
    return $score;
}
?>
