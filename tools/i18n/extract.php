<?php
// Usage: php extract.php <plugin_dir>  -> prints JSON list of {msgid, plural, refs, comment}
$dir = rtrim($argv[1], '/');
$funcs = ['__'=>1,'_e'=>1,'esc_html__'=>1,'esc_html_e'=>1,'esc_attr__'=>1,'esc_attr_e'=>1,'_n'=>2];
$out = [];
$files = array_merge([$dir.'/hexasyncintegration.php'], glob($dir.'/src/*/*.php'));
foreach ($files as $file) {
    $rel = substr($file, strlen($dir)+1);
    $toks = token_get_all(file_get_contents($file));
    $lastComment = null; $lastCommentLine = -10;
    for ($i=0; $i<count($toks); $i++) {
        $t = $toks[$i];
        if (is_array($t) && $t[0]===T_COMMENT && strpos($t[1],'translators:')!==false) {
            $lastComment = trim(preg_replace('#^/\*|\*/$#','',$t[1])); $lastCommentLine = $t[2];
        }
        if (!is_array($t) || $t[0]!==T_STRING || !isset($funcs[$t[1]])) continue;
        $j=$i+1; while (is_array($toks[$j]) && $toks[$j][0]===T_WHITESPACE) $j++;
        if ($toks[$j] !== '(') continue;
        $args=[]; $depth=0;
        for ($k=$j+1; $k<count($toks); $k++) {
            $x=$toks[$k];
            if ($x==='('||$x==='[') $depth++;
            if ($x===')'||$x===']') { if ($depth===0) break; $depth--; }
            if ($depth===0 && is_array($x) && $x[0]===T_CONSTANT_ENCAPSED_STRING) $args[] = eval('return '.$x[1].';');
        }
        $id = $args[0];
        $domain = end($args);
        if ($domain !== 'hexasyncintegration') { fwrite(STDERR, "BAD DOMAIN $rel:{$t[2]} $id\n"); }
        $key = $id;
        if (!isset($out[$key])) $out[$key] = ['msgid'=>$id,'plural'=>$funcs[$t[1]]===2?$args[1]:null,'refs'=>[],'comment'=>null];
        $out[$key]['refs'][] = "$rel:{$t[2]}";
        if ($lastComment && $t[2]-$lastCommentLine<=3) $out[$key]['comment']=$lastComment;
        $lastComment = null;
    }
}
echo json_encode(array_values($out), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
