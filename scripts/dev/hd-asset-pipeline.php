#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * HiveNova HD Asset Pipeline (Local, $0 post-processing & procedural animation)
 *
 * Designed for fast, low-cost 2x2 sprite-grid redraws (4 icons per image-gen call)
 * or single 1x1 redraws, matching PR #733 WebP specs:
 *   - Cutouts: long side 240px, hard 0/255 alpha, WebP q=80 method=6 sharp_yuv alpha_q=100
 *   - Plates:  240x240 full-bleed opaque WebP q=80 method=6 sharp_yuv
 *   - Optional $0 local procedural animations encoded with libwebp_anim delta compression
 *
 * Dependencies (already present locally; no host installs required):
 *   - PHP 8.x with GD extension
 *   - ImageMagick (`magick`)
 *   - FFmpeg (`ffmpeg`, only required for `animate`)
 *
 * Usage:
 *   php scripts/dev/hd-asset-pipeline.php prep    --ids=410,409,503,113 [--style=203,216,401,406] --out=/tmp/batch
 *   php scripts/dev/hd-asset-pipeline.php slice   --input=grid.jpg --ids=410,409,503,113 --out=/tmp/batch/out [options]
 *   php scripts/dev/hd-asset-pipeline.php animate --in=/tmp/batch/out --out=/tmp/batch/anim --spec=410:cyan-core,409:purple-shield [--gif]
 *   php scripts/dev/hd-asset-pipeline.php compare --ids=410,409,503,113 --new=/tmp/batch/out --out=/tmp/batch/compare.png [--hd-dir=...]
 */

$repoRoot = dirname(__DIR__, 2);

main($argv, $repoRoot);

function main(array $argv, string $repoRoot): void
{
    $cmd = $argv[1] ?? 'help';
    $opts = parseCliOptions(array_slice($argv, 2));

    switch ($cmd) {
        case 'prep':
            cmdPrep($opts, $repoRoot);
            break;
        case 'slice':
            cmdSlice($opts);
            break;
        case 'animate':
            cmdAnimate($opts);
            break;
        case 'plume':
            cmdPlume($opts);
            break;
        case 'compare':
            cmdCompare($opts, $repoRoot);
            break;
        case 'help':
        case '--help':
        case '-h':
        default:
            printUsage();
            exit($cmd === 'help' || $cmd === '--help' || $cmd === '-h' ? 0 : 1);
    }
}

function printUsage(): void
{
    echo <<<TEXT
HiveNova HD Asset Pipeline

Commands:
  prep      Build a 2x2 (or 1x1) classic reference sheet and optional style-anchor sheet.
            --ids=ID1[,ID2,ID3,ID4]   Element IDs (1 or up to 4 for 2x2 grid)
            --out=DIR                 Output directory for ref_classic.png / ref_style.png
            [--style=ID1,ID2,...]     Optional HD style-anchor IDs or file paths
            [--orig-dir=DIR]          Classic gebaeude dir (default: styles/theme/nova/gebaeude)
            [--hd-dir=DIR]            Existing HD dir for style refs (default: styles/theme/nova/gebaeude-hd)

  slice     Slice a 1x1 or 2x2 generated image into 240px WebPs (cutout or full-bleed plate).
            --input=FILE              Generated 1x1 or 2x2 source image
            --ids=ID1[,ID2,ID3,ID4]   IDs in row-major order: TL, TR, BL, BR
            --out=DIR                 Output directory ({id}.webp + {id}_base.png)
            [--mode=cutout|plate]     cutout (chroma-keyed alpha) or plate (240x240 cover). Default: cutout
            [--key=green|magenta]     Chroma-key color for cutout mode. Default: green
            [--fuzz=22]               Floodfill fuzz percent (or per-ID: --fuzz-map=503:28,409:24)
            [--erode=1.2]             Alpha erosion radius in px before resize. Default: 1.2
            [--protect-interior=IDs]  Comma-separated IDs to skip global despill (e.g. Earth in 409)
            [--size=240]              Target long side / plate size in px. Default: 240
            [--quality=80]            WebP quality. Default: 80

  animate   Synthesize $0 local procedural loop animations into delta-compressed animated WebP (+ optional GIF).
            --in=DIR                  Directory containing {id}_base.png (or {id}.webp) from `slice`
            --out=DIR                 Output directory ({id}.webp + optional preview_anim.gif)
            --spec=ID:PRESET[,...]    Presets: cyan-core, energy-reactor, purple-shield,
                                               flame-flicker, vertical-pulse, warm-core,
                                               beam-down, metal-sheen, hull-lights
            [--frames=16]             Frames per seamless loop. Default: 16
            [--fps=12]                Playback FPS. Default: 12
            [--quality=75]            libwebp_anim quality. Default: 75
            [--gif]                   Also render a combined animated GIF preview on the game radial plate

  plume     Render $0 animated volumetric engine drive plumes for a ship cutout into animated WebP.
            --input=FILE              Source ship PNG/WebP cutout
            --out=FILE                Output animated WebP path
            --nozzles=x:y:r:len[,...] Nozzle list in 240x240 space (x, y, radius, plume length)
            --dir=dx,dy               Normalized thrust exhaust direction vector (e.g. 0.8,-0.6)
            [--color=cyan|amber|blue] Plume color palette. Default: cyan
            [--layer=behind|front]    Composite plume behind hull or over visible rear bells. Default: behind
            [--shift=dx,dy]           Offset ship inside 240x240 canvas to give plumes room. Default: 0,0
            [--ship-size=192]         Max ship dimension inside 240x240 canvas. Default: 192
            [--frames=16]             Frames per seamless loop. Default: 16
            [--fps=14]                Playback FPS. Default: 14
            [--quality=76]            libwebp_anim quality. Default: 76

  compare   Build a labeled Classic | Existing HD | New Output (180px + 120px) comparison PNG.
            --ids=ID1,ID2,...         Element IDs to compare
            --new=DIR                 Directory containing new {id}.webp files
            --out=FILE                Output comparison PNG path
            [--orig-dir=DIR]          Classic gebaeude dir
            [--hd-dir=DIR]            Existing HD dir to compare against (optional)
            [--plate-ids=IDs]         Comma-separated IDs rendered with cover (no radial plate)

TEXT;
}

/** @return array<string, string|bool> */
function parseCliOptions(array $args): array
{
    $opts = [];
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $eq = strpos($arg, '=');
        if ($eq === false) {
            $opts[substr($arg, 2)] = true;
        } else {
            $opts[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        }
    }
    return $opts;
}

function requireOpt(array $opts, string $name): string
{
    if (!isset($opts[$name]) || !is_string($opts[$name]) || $opts[$name] === '') {
        fwrite(STDERR, "Error: missing required option --{$name}=...\n");
        exit(1);
    }
    return $opts[$name];
}

function parseList(?string $val): array
{
    if ($val === null || trim($val) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $val)), fn($s) => $s !== ''));
}

/** @return array<string, float> */
function parseFloatMap(?string $val): array
{
    $map = [];
    foreach (parseList($val) as $pair) {
        if (str_contains($pair, ':')) {
            [$k, $v] = explode(':', $pair, 2);
            $map[trim($k)] = (float)trim($v);
        }
    }
    return $map;
}

function runCmd(string $cmd): string
{
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException("Failed to start command: {$cmd}");
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        throw new RuntimeException("Command failed (exit {$code}): {$cmd}\n{$stderr}");
    }
    return trim((string)$stdout);
}

function findClassicFile(string $origDir, string $id): ?string
{
    foreach (['gif', 'jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $p = "{$origDir}/{$id}.{$ext}";
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: {$dir}");
    }
}

/* =========================== PREP =========================== */

function cmdPrep(array $opts, string $repoRoot): void
{
    $ids = parseList(requireOpt($opts, 'ids'));
    $outDir = requireOpt($opts, 'out');
    $origDir = is_string($opts['orig-dir'] ?? null) ? $opts['orig-dir'] : "{$repoRoot}/styles/theme/nova/gebaeude";
    $hdDir = is_string($opts['hd-dir'] ?? null) ? $opts['hd-dir'] : "{$repoRoot}/styles/theme/nova/gebaeude-hd";
    $styleRefs = parseList(is_string($opts['style'] ?? null) ? $opts['style'] : null);

    if (count($ids) < 1 || count($ids) > 4) {
        fwrite(STDERR, "Error: --ids must contain 1 to 4 element IDs.\n");
        exit(1);
    }

    ensureDir($outDir);
    $tiles = [];
    foreach ($ids as $id) {
        $src = is_file($id) ? $id : findClassicFile($origDir, $id);
        if ($src === null) {
            fwrite(STDERR, "Error: classic source not found for ID '{$id}' in {$origDir}\n");
            exit(1);
        }
        $tile = "{$outDir}/_tmp_classic_{$id}.png";
        runCmd(sprintf(
            'magick %s -coalesce -resize 240x240 -background "#0e121b" -gravity center -extent 256x256 PNG24:%s',
            escapeshellarg($src . '[0]'),
            escapeshellarg($tile)
        ));
        $tiles[] = $tile;
    }

    $classicOut = "{$outDir}/ref_classic.png";
    assembleGrid($tiles, $classicOut);
    echo "Wrote {$classicOut}\n";

    if (count($styleRefs) > 0) {
        $styleTiles = [];
        foreach ($styleRefs as $idx => $ref) {
            $src = is_file($ref) ? $ref : "{$hdDir}/{$ref}.webp";
            if (!is_file($src)) {
                fwrite(STDERR, "Error: style ref not found: {$src}\n");
                exit(1);
            }
            $tile = "{$outDir}/_tmp_style_{$idx}.png";
            runCmd(sprintf(
                'magick -size 256x256 radial-gradient:"#2c3548-#12151f" \( %s -resize 240x240 \) -gravity center -composite PNG24:%s',
                escapeshellarg($src),
                escapeshellarg($tile)
            ));
            $styleTiles[] = $tile;
        }
        $styleOut = "{$outDir}/ref_style.png";
        assembleGrid($styleTiles, $styleOut);
        echo "Wrote {$styleOut}\n";
    }

    foreach (glob("{$outDir}/_tmp_*.png") ?: [] as $tmp) {
        @unlink($tmp);
    }
}

function assembleGrid(array $tiles, string $outPath): void
{
    if (count($tiles) === 1) {
        copy($tiles[0], $outPath);
        return;
    }
    while (count($tiles) < 4) {
        $blank = dirname($outPath) . '/_tmp_blank_' . count($tiles) . '.png';
        runCmd('magick -size 256x256 xc:"#0e121b" ' . escapeshellarg($blank));
        $tiles[] = $blank;
    }
    runCmd(sprintf(
        'magick \( %s %s +append \) \( %s %s +append \) -append PNG24:%s',
        escapeshellarg($tiles[0]),
        escapeshellarg($tiles[1]),
        escapeshellarg($tiles[2]),
        escapeshellarg($tiles[3]),
        escapeshellarg($outPath)
    ));
}

/* =========================== SLICE =========================== */

function cmdSlice(array $opts): void
{
    $input = requireOpt($opts, 'input');
    $ids = parseList(requireOpt($opts, 'ids'));
    $outDir = requireOpt($opts, 'out');
    $mode = is_string($opts['mode'] ?? null) ? $opts['mode'] : 'cutout';
    $key = is_string($opts['key'] ?? null) ? $opts['key'] : 'green';
    $defaultFuzz = isset($opts['fuzz']) ? (float)$opts['fuzz'] : 22.0;
    $fuzzMap = parseFloatMap(is_string($opts['fuzz-map'] ?? null) ? $opts['fuzz-map'] : null);
    $erode = isset($opts['erode']) ? (float)$opts['erode'] : 1.2;
    $protectInterior = array_flip(parseList(is_string($opts['protect-interior'] ?? null) ? $opts['protect-interior'] : null));
    $size = isset($opts['size']) ? (int)$opts['size'] : 240;
    $quality = isset($opts['quality']) ? (int)$opts['quality'] : 80;

    if (!is_file($input)) {
        fwrite(STDERR, "Error: input file not found: {$input}\n");
        exit(1);
    }
    if (count($ids) < 1 || count($ids) > 4) {
        fwrite(STDERR, "Error: --ids must contain 1 to 4 IDs.\n");
        exit(1);
    }

    ensureDir($outDir);
    [$w, $h] = array_map('intval', explode(' ', runCmd('magick identify -format "%w %h" ' . escapeshellarg($input))));
    $isGrid = count($ids) > 1;
    $qw = $isGrid ? intdiv($w, 2) : $w;
    $qh = $isGrid ? intdiv($h, 2) : $h;

    $crops = $isGrid
        ? [
            "{$qw}x{$qh}+0+0",
            "{$qw}x{$qh}+{$qw}+0",
            "{$qw}x{$qh}+0+{$qh}",
            "{$qw}x{$qh}+{$qw}+{$qh}",
        ]
        : ["{$w}x{$h}+0+0"];

    echo "Slicing {$input} ({$w}x{$h}) -> " . count($ids) . " asset(s) [mode={$mode}, key={$key}]\n";

    foreach ($ids as $idx => $id) {
        $rawPng = "{$outDir}/_raw_{$id}.png";
        $basePng = "{$outDir}/{$id}_base.png";
        $webpPath = "{$outDir}/{$id}.webp";

        runCmd(sprintf(
            'magick %s -crop %s +repage %s',
            escapeshellarg($input),
            escapeshellarg($crops[$idx]),
            escapeshellarg($rawPng)
        ));

        if ($mode === 'plate') {
            runCmd(sprintf(
                'magick %s -resize %dx%d^ -gravity center -extent %dx%d PNG24:%s',
                escapeshellarg($rawPng),
                $size, $size, $size, $size,
                escapeshellarg($basePng)
            ));
            runCmd(sprintf(
                'magick %s -define webp:method=6 -define webp:use-sharp-yuv=true -quality %d %s',
                escapeshellarg($basePng),
                $quality,
                escapeshellarg($webpPath)
            ));
            $info = runCmd('magick identify -format "%wx%h %[channels] %b" ' . escapeshellarg($webpPath));
            echo "  {$id}.webp [plate]:  {$info}\n";
        } else {
            $fuzz = $fuzzMap[$id] ?? $defaultFuzz;
            $xMax = $qw - 3;
            $yMax = $qh - 3;

            $despillExpr = '';
            if (!isset($protectInterior[$id])) {
                if ($key === 'green') {
                    $despillExpr = '-channel G -fx "min(g, max(r,b)*1.04)" +channel';
                } elseif ($key === 'magenta') {
                    $despillExpr = '-channel RB -fx "min(u, max(g, (r+b)*0.45)*1.05)" +channel';
                }
            }

            $erodePart = $erode > 0 ? sprintf('-channel A -morphology Erode Disk:%.2f +channel', $erode) : '';

            runCmd(sprintf(
                'magick %s -alpha set -fuzz %s%% -fill none ' .
                '-draw "color 2,2 floodfill" -draw "color %d,2 floodfill" ' .
                '-draw "color 2,%d floodfill" -draw "color %d,%d floodfill" ' .
                '%s %s -trim +repage -resize %dx%d -channel A -threshold 50%% +channel PNG32:%s',
                escapeshellarg($rawPng),
                $fuzz,
                $xMax, $yMax, $xMax, $yMax,
                $erodePart,
                $despillExpr,
                $size, $size,
                escapeshellarg($basePng)
            ));

            runCmd(sprintf(
                'magick %s -define webp:method=6 -define webp:alpha-quality=100 -define webp:use-sharp-yuv=true -quality %d %s',
                escapeshellarg($basePng),
                $quality,
                escapeshellarg($webpPath)
            ));

            $info = runCmd('magick identify -format "%wx%h %[channels] %b" ' . escapeshellarg($webpPath));
            $fringeFx = $key === 'magenta'
                ? '(a>0.5 && r>0.65 && g<0.35 && b>0.65) ? 1 : 0'
                : '(a>0.5 && g>r+0.20 && g>b+0.20) ? 1 : 0';
            $fringe = (int)round((float)runCmd(sprintf(
                'magick %s -alpha on -fx %s -format "%%[fx:mean*w*h]" info:',
                escapeshellarg($webpPath),
                escapeshellarg($fringeFx)
            )));
            echo "  {$id}.webp [cutout]: {$info} | {$key}-fringe px={$fringe}\n";
        }

        @unlink($rawPng);
    }
}

/* =========================== ANIMATE =========================== */

function cmdAnimate(array $opts): void
{
    $inDir = requireOpt($opts, 'in');
    $outDir = requireOpt($opts, 'out');
    $specStr = requireOpt($opts, 'spec');
    $numFrames = isset($opts['frames']) ? max(4, (int)$opts['frames']) : 16;
    $fps = isset($opts['fps']) ? max(2, (int)$opts['fps']) : 12;
    $quality = isset($opts['quality']) ? (int)$opts['quality'] : 75;
    $makeGif = !empty($opts['gif']);

    $specs = [];
    foreach (parseList($specStr) as $item) {
        if (!str_contains($item, ':')) {
            fwrite(STDERR, "Error: invalid --spec entry '{$item}', expected ID:PRESET\n");
            exit(1);
        }
        [$id, $preset] = array_map('trim', explode(':', $item, 2));
        $specs[$id] = $preset;
    }

    ensureDir($outDir);
    $previewDir = "{$outDir}/_preview_frames";
    if ($makeGif) {
        ensureDir($previewDir);
    }

    $loaded = [];
    $centers = [];
    foreach ($specs as $id => $preset) {
        $srcPng = "{$inDir}/{$id}_base.png";
        if (!is_file($srcPng)) {
            $fallbackWebp = "{$inDir}/{$id}.webp";
            if (!is_file($fallbackWebp)) {
                fwrite(STDERR, "Error: missing {$srcPng} or {$fallbackWebp}\n");
                exit(1);
            }
            $srcPng = "{$outDir}/_tmp_src_{$id}.png";
            runCmd(sprintf('magick %s PNG32:%s', escapeshellarg($fallbackWebp), escapeshellarg($srcPng)));
        }
        $loaded[$id] = loadPngPixels($srcPng);
        $centers[$id] = findAccentCenter($loaded[$id][2], $loaded[$id][0], $loaded[$id][1], $preset);
        ensureDir("{$outDir}/_frames_{$id}");
    }

    $plate200 = $makeGif ? makePlateBg(200) : null;
    $ids = array_keys($specs);

    for ($f = 0; $f < $numFrames; $f++) {
        $phase = ($f / $numFrames) * 2.0 * M_PI;
        $frameFiles = [];

        foreach ($specs as $id => $preset) {
            [$w, $h, $src] = $loaded[$id];
            $dst = applyPresetFrame($src, $w, $h, $preset, $phase, $centers[$id]);
            $fp = sprintf('%s/_frames_%s/f_%02d.png', $outDir, $id, $f);
            saveFramePng($w, $h, $dst, $fp);
            $frameFiles[$id] = $fp;
        }

        if ($makeGif && $plate200 !== null) {
            $cols = count($ids);
            $pw = 16 + $cols * 210 + 6;
            $ph = 248;
            $canv = imagecreatetruecolor($pw, $ph);
            $bgc = imagecolorallocate($canv, 14, 17, 24);
            $bdr = imagecolorallocate($canv, 68, 82, 110);
            $txt = imagecolorallocate($canv, 232, 238, 248);
            imagefilledrectangle($canv, 0, 0, $pw, $ph, $bgc);
            imagestring($canv, 4, 16, 8, "Local Procedural Animation Preview ({$numFrames}f @ {$fps}fps)", $txt);

            foreach ($ids as $idx => $id) {
                $ox = 16 + $idx * 210;
                $oy = 32;
                imagecopy($canv, $plate200, $ox, $oy, 0, 0, 200, 200);
                $el = imagecreatefrompng($frameFiles[$id]);
                $ew = imagesx($el);
                $eh = imagesy($el);
                $scale = min(192 / $ew, 192 / $eh);
                $nw = (int)round($ew * $scale);
                $nh = (int)round($eh * $scale);
                imagealphablending($canv, true);
                imagecopyresampled($canv, $el, $ox + (int)((200 - $nw) / 2), $oy + (int)((200 - $nh) / 2), 0, 0, $nw, $nh, $ew, $eh);
                imagedestroy($el);
                imagerectangle($canv, $ox, $oy, $ox + 199, $oy + 199, $bdr);
                imagestring($canv, 3, $ox + 4, $oy + 203, "{$id} ({$specs[$id]})", $txt);
            }
            imagepng($canv, sprintf('%s/p_%02d.png', $previewDir, $f));
            imagedestroy($canv);
        }
    }

    if ($plate200 !== null) {
        imagedestroy($plate200);
    }

    foreach ($ids as $id) {
        $webpOut = "{$outDir}/{$id}.webp";
        runCmd(sprintf(
            'ffmpeg -y -v error -framerate %d -i %s -c:v libwebp_anim -loop 0 -lossless 0 -compression_level 6 -q:v %d -pix_fmt yuva420p %s',
            $fps,
            escapeshellarg("{$outDir}/_frames_{$id}/f_%02d.png"),
            $quality,
            escapeshellarg($webpOut)
        ));
        $kb = round(filesize($webpOut) / 1024, 1);
        echo "  {$webpOut} ({$specs[$id]}): {$kb} KB\n";
        removeDir("{$outDir}/_frames_{$id}");
    }

    if ($makeGif) {
        $gifOut = "{$outDir}/preview_anim.gif";
        runCmd(sprintf(
            'ffmpeg -y -v error -framerate %d -i %s -vf "split[s0][s1];[s0]palettegen=max_colors=224:stats_mode=diff[p];[s1][p]paletteuse=dither=bayer:bayer_scale=3" -loop 0 %s',
            $fps,
            escapeshellarg("{$previewDir}/p_%02d.png"),
            escapeshellarg($gifOut)
        ));
        $kb = round(filesize($gifOut) / 1024, 1);
        echo "  Preview GIF: {$gifOut} ({$kb} KB)\n";
        removeDir($previewDir);
    }

    foreach (glob("{$outDir}/_tmp_src_*.png") ?: [] as $tmp) {
        @unlink($tmp);
    }
}

function findAccentCenter(array $px, int $w, int $h, string $preset): array
{
    $sx = 0.0;
    $sy = 0.0;
    $sw = 0.0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = $px[$y][$x];
            if ($a >= 64) {
                continue;
            }
            if ($preset === 'warm-core') {
                $score = max(0, ($r - $b) * 0.7 + ($g - $b) * 0.3);
                if ($score > 50 && $r > 160) {
                    $wt = $score * $r;
                    $sx += $x * $wt;
                    $sy += $y * $wt;
                    $sw += $wt;
                }
            } else {
                $score = max(0, ($b - $r) * 0.7 + ($g - $r) * 0.3);
                if ($score > 45 && $b > 130) {
                    $wt = $score * $b;
                    $sx += $x * $wt;
                    $sy += $y * $wt;
                    $sw += $wt;
                }
            }
        }
    }
    return $sw > 0 ? [$sx / $sw, $sy / $sw] : [$w * 0.5, $h * 0.5];
}

function applyPresetFrame(array $src, int $w, int $h, string $preset, float $phase, array $center): array
{
    $dst = $src;
    $pulse = 0.5 + 0.5 * sin($phase);
    [$acx, $acy] = $center;

    switch ($preset) {
        case 'cyan-core':
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $d = hypot($x - $acx, $y - $acy);
                    if ($b > $r + 25 && $b > 100 && $d < 52) {
                        $ang = atan2($y - $acy, $x - $acx);
                        $crackle = 0.85 + 0.15 * sin($ang * 4.0 + $phase * 2.0 + $d * 0.25);
                        $boost = (0.78 + 0.52 * $pulse) * $crackle;
                        $add = 38.0 * $pulse * max(0.0, 1.0 - $d / 32.0);
                        $dst[$y][$x] = [$r * $boost + $add * 0.5, $g * $boost + $add * 0.9, $b * $boost + $add * 1.1, $a];
                    } elseif ($d < 78) {
                        $fo = max(0.0, 1.0 - $d / 78.0);
                        $glow = $fo * $fo * $pulse * 34.0;
                        $dst[$y][$x] = [$r + $glow * 0.15, $g + $glow * 0.65, $b + $glow, $a];
                    }
                }
            }
            break;

        case 'energy-reactor':
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $d = hypot($x - $acx, $y - $acy);
                    $ang = atan2($y - $acy, $x - $acx);
                    if ($b > $r + 22 && $b > 95 && $d < 56) {
                        $arc = 0.82 + 0.22 * sin($ang * 6.0 - $phase * 2.0 + $d * 0.22);
                        $boost = (0.76 + 0.55 * $pulse) * $arc;
                        $add = 42.0 * $pulse * max(0.0, 1.0 - $d / 34.0);
                        $dst[$y][$x] = [$r * $boost + $add * 0.55, $g * $boost + $add * 0.9, $b * $boost + $add * 1.1, $a];
                    } elseif ($r > $b + 25 && $r > 70 && $d > 28 && $d < 86) {
                        $cw = 0.5 + 0.5 * sin($d * 0.14 - $phase * 2.0);
                        $boost = 0.90 + 0.32 * $cw;
                        $dst[$y][$x] = [$r * $boost + 14 * $cw, $g * $boost + 7 * $cw, $b * $boost + 10 * $pulse, $a];
                    } elseif ($d < 85) {
                        $fo = max(0.0, 1.0 - $d / 85.0);
                        $glow = $fo * $fo * $pulse * 26.0;
                        $dst[$y][$x] = [$r + $glow * 0.15, $g + $glow * 0.6, $b + $glow, $a];
                    }
                }
            }
            break;

        case 'purple-shield':
            $cx = $w * 0.5;
            $cy = $h * 0.5;
            $earthR = min($w, $h) * 0.245;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $d = hypot($x - $cx, $y - $cy);
                    if ($d > $earthR && $r > $g + 12 && $b > $g + 22) {
                        $ny = abs($y - $cy) / ($h * 0.5);
                        $wave = 0.5 + 0.5 * sin($ny * 7.5 - $phase * 2.0 + ($d / 28.0));
                        $shimmer = 0.78 + 0.58 * pow($wave, 1.6);
                        $add = 26.0 * pow($wave, 2.2);
                        $dst[$y][$x] = [$r * $shimmer + $add * 0.85, $g * $shimmer + $add * 0.35, $b * $shimmer + $add * 1.15, $a];
                    }
                }
            }
            break;

        case 'flame-flicker':
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    if ($r > 155 && ($r - $b) > 35 && $g > 75) {
                        $along = ($h - $y) * 0.72 + $x * 0.68;
                        $w1 = 0.5 + 0.5 * sin($along * 0.28 + $phase * 3.0);
                        $w2 = 0.5 + 0.5 * sin($along * 0.52 + $phase * 2.0 + $x * 0.08);
                        $flick = 0.78 + 0.42 * (0.65 * $w1 + 0.35 * $w2);
                        $hot = 28.0 * $w1 * $w2;
                        $dst[$y][$x] = [$r * $flick + $hot, $g * $flick + $hot * 0.85, $b * $flick + $hot * 0.4, $a];
                    }
                }
            }
            break;

        case 'vertical-pulse':
            $bandY = $h * (1.0 - ($phase / (2.0 * M_PI)));
            for ($y = 0; $y < $h; $y++) {
                $dy = abs($y - $bandY);
                $dy = min($dy, $h - $dy);
                $band = exp(-($dy * $dy) / 280.0);
                if ($band < 0.02) continue;
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $dx = abs($x - $w * 0.5) / ($w * 0.22);
                    if ($dx > 1.2) continue;
                    $colMask = max(0.0, 1.0 - $dx * $dx * 0.7);
                    $isLit = ($r + $g > $b * 1.6) ? 1.0 : 0.45;
                    $boost = $band * $colMask * $isLit;
                    $dst[$y][$x] = [$r + 75 * $boost, $g + 85 * $boost, $b + 20 * $boost, $a];
                }
            }
            break;

        case 'warm-core':
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $d = hypot($x - $acx, $y - $acy);
                    if ($r > $b + 30 && $r > 130 && $d < 56) {
                        $boost = 0.85 + 0.38 * $pulse;
                        $add = 32.0 * $pulse * max(0.0, 1.0 - $d / 36.0);
                        $dst[$y][$x] = [$r * $boost + $add, $g * $boost + $add * 0.8, $b * $boost + $add * 0.3, $a];
                    } elseif ($d < 78) {
                        $fo = max(0.0, 1.0 - $d / 78.0);
                        $glow = $fo * $fo * $pulse * 22.0;
                        $dst[$y][$x] = [$r + $glow, $g + $glow * 0.72, $b + $glow * 0.2, $a];
                    }
                }
            }
            break;

        case 'beam-down':
            // Streaming energy pulses traveling downward along bright cyan/blue beams & glowing thrusters
            for ($y = 0; $y < $h; $y++) {
                $stream = 0.5 + 0.5 * sin(($y * 0.22) - ($phase * 2.0));
                $stream2 = 0.5 + 0.5 * sin(($y * 0.45) - ($phase * 3.0));
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $blueLead = $b - $r;
                    $lum = 0.3 * $r + 0.59 * $g + 0.11 * $b;
                    // Target luminous cyan/blue beam & thruster glow (skip dim dark sky/horizon greys)
                    if ($blueLead > 28 && $b > 135 && $y > $h * 0.45) {
                        $wgt = min(1.0, ($blueLead - 28) / 80.0);
                        $wave = 0.65 * $stream + 0.35 * $stream2;
                        $boost = 0.84 + 0.44 * $wave * $wgt;
                        $add = 40.0 * pow($wave, 1.8) * $wgt * ($lum > 120 ? 1.0 : 0.4);
                        $dst[$y][$x] = [$r * $boost + $add * 0.45, $g * $boost + $add * 0.9, $b * $boost + $add * 1.1, $a];
                    }
                }
            }
            break;

        case 'metal-sheen':
            // Subtle diagonal specular light sweep across armored metallic surfaces
            $diagSpan = $w + $h;
            $pos = ($phase / (2.0 * M_PI)) * $diagSpan;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $diag = $x + $y;
                    $dist = abs($diag - $pos);
                    $dist = min($dist, $diagSpan - $dist);
                    $band = exp(-($dist * $dist) / 420.0);
                    if ($band < 0.02) continue;
                    $lum = (0.3 * $r + 0.59 * $g + 0.11 * $b) / 255.0;
                    // Stronger highlight on existing metallic bevels/edges
                    $add = $band * (18.0 + 46.0 * $lum);
                    $dst[$y][$x] = [$r + $add * 0.92, $g + $add * 0.98, $b + $add * 1.08, $a];
                }
            }
            break;

        case 'hull-lights':
            // Subtle porthole/nav-light twinkle + gentle diagonal ambient sheen across dark hulls
            $diagSpan = $w + $h;
            $pos = ($phase / (2.0 * M_PI)) * $diagSpan;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    if ($a >= 64) continue;
                    $lum = 0.3 * $r + 0.59 * $g + 0.11 * $b;
                    $sat = max($r, $g, $b) - min($r, $g, $b);
                    if ($lum > 175 && $sat < 45) {
                        // Bright porthole / beacon pixels gently pulse staggered by x
                        $tw = 0.5 + 0.5 * sin($phase * 2.0 + $x * 0.18);
                        $boost = 0.82 + 0.36 * $tw;
                        $dst[$y][$x] = [$r * $boost + 24 * $tw, $g * $boost + 26 * $tw, $b * $boost + 32 * $tw, $a];
                    } else {
                        $dist = abs(($x + $y) - $pos);
                        $dist = min($dist, $diagSpan - $dist);
                        $band = exp(-($dist * $dist) / 550.0);
                        if ($band > 0.02) {
                            $add = $band * (8.0 + 22.0 * ($lum / 255.0));
                            $dst[$y][$x] = [$r + $add * 0.9, $g + $add, $b + $add * 0.92, $a];
                        }
                    }
                }
            }
            break;

        default:
            throw new RuntimeException("Unknown animation preset: {$preset}");
    }

    return $dst;
}

/* =========================== PLUME =========================== */

function cmdPlume(array $opts): void
{
    $input = requireOpt($opts, 'input');
    $outPath = requireOpt($opts, 'out');
    $nozzleStr = requireOpt($opts, 'nozzles');
    $dirStr = requireOpt($opts, 'dir');
    $color = is_string($opts['color'] ?? null) ? $opts['color'] : 'cyan';
    $layer = is_string($opts['layer'] ?? null) ? $opts['layer'] : 'behind';
    $shiftStr = is_string($opts['shift'] ?? null) ? $opts['shift'] : '0,0';
    $shipSize = isset($opts['ship-size']) ? (int)$opts['ship-size'] : 192;
    $numFrames = isset($opts['frames']) ? max(6, (int)$opts['frames']) : 16;
    $fps = isset($opts['fps']) ? max(4, (int)$opts['fps']) : 14;
    $quality = isset($opts['quality']) ? (int)$opts['quality'] : 76;

    $canvasSize = 240;
    [$dirX, $dirY] = array_map('floatval', explode(',', $dirStr, 2));
    $dirLen = hypot($dirX, $dirY);
    if ($dirLen < 1e-6) {
        throw new RuntimeException("Invalid --dir vector: {$dirStr}");
    }
    $dirX /= $dirLen;
    $dirY /= $dirLen;
    $perpX = -$dirY;
    $perpY = $dirX;

    [$shiftX, $shiftY] = array_map('intval', explode(',', $shiftStr, 2));

    $nozzles = [];
    foreach (parseList($nozzleStr) as $idx => $entry) {
        $parts = explode(':', $entry);
        if (count($parts) !== 4) {
            throw new RuntimeException("Invalid nozzle '{$entry}', expected x:y:r:len");
        }
        $nozzles[] = [
            'x' => (float)$parts[0],
            'y' => (float)$parts[1],
            'r' => (float)$parts[2],
            'len' => (float)$parts[3],
            'seed' => $idx * 1.73,
        ];
    }

    $palettes = [
        'cyan'  => ['outer' => [30, 175, 255], 'mid' => [115, 232, 255], 'core' => [245, 253, 255]],
        'blue'  => ['outer' => [45, 115, 255], 'mid' => [110, 185, 255], 'core' => [235, 246, 255]],
        'amber' => ['outer' => [255, 105, 20], 'mid' => [255, 195, 70],  'core' => [255, 252, 230]],
    ];
    $pal = $palettes[$color] ?? $palettes['cyan'];

    ensureDir(dirname($outPath));
    $tmpDir = sys_get_temp_dir() . '/hn_plume_' . getmypid() . '_' . mt_rand(1000, 9999);
    ensureDir($tmpDir);

    // Prepare 240x240 transparent ship canvas via magick (handles PNG or WebP cleanly)
    $shipPng = "{$tmpDir}/ship_240.png";
    $baseCanvas = imagecreatetruecolor($canvasSize, $canvasSize);
    imagealphablending($baseCanvas, false);
    imagesavealpha($baseCanvas, true);
    $trans = imagecolorallocatealpha($baseCanvas, 0, 0, 0, 127);
    imagefilledrectangle($baseCanvas, 0, 0, $canvasSize, $canvasSize, $trans);

    $srcPng = $input;
    if (!str_ends_with(strtolower($input), '.png')) {
        $srcPng = "{$tmpDir}/src.png";
        runCmd(sprintf('magick %s PNG32:%s', escapeshellarg($input), escapeshellarg($srcPng)));
    }
    $el = imagecreatefrompng($srcPng);
    $ew = imagesx($el);
    $eh = imagesy($el);
    $scale = min($shipSize / $ew, $shipSize / $eh);
    $nw = (int)round($ew * $scale);
    $nh = (int)round($eh * $scale);
    $ox = (int)round(($canvasSize - $nw) / 2) + $shiftX;
    $oy = (int)round(($canvasSize - $nh) / 2) + $shiftY;
    imagealphablending($baseCanvas, true);
    imagecopyresampled($baseCanvas, $el, $ox, $oy, 0, 0, $nw, $nh, $ew, $eh);
    imagedestroy($el);
    imagepng($baseCanvas, $shipPng);
    imagedestroy($baseCanvas);

    [$w, $h, $shipPx] = loadPngPixels($shipPng);

    for ($f = 0; $f < $numFrames; $f++) {
        $phase = ($f / $numFrames) * 2.0 * M_PI;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $tr = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $tr);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                [$sr, $sg, $sb, $sa] = $shipPx[$y][$x];
                $shipAlpha = (127.0 - $sa) / 127.0; // 0..1

                // Accumulate volumetric plume radiance + opacity from all nozzles
                $pr = 0.0;
                $pg = 0.0;
                $pb = 0.0;
                $pa = 0.0;

                foreach ($nozzles as $nz) {
                    $vx = $x - $nz['x'];
                    $vy = $y - $nz['y'];
                    $along = $vx * $dirX + $vy * $dirY;
                    $across = $vx * $perpX + $vy * $perpY;

                    $seed = $nz['seed'];
                    // Seamless multi-harmonic thrust oscillation (length + subtle flicker)
                    $lenScale = 0.88 + 0.16 * sin($phase * 2.0 + $seed) + 0.07 * sin($phase * 3.0 - $seed * 1.3);
                    $curLen = $nz['len'] * $lenScale;
                    if ($along < -$nz['r'] * 1.1 || $along > $curLen * 1.25) {
                        continue;
                    }

                    $t = $along / max(1.0, $curLen); // 0 at nozzle, 1 at tip
                    if ($t < 0.0) {
                        // Soft circular glow right inside/around the nozzle lip
                        $d = hypot($vx, $vy) / max(1.0, $nz['r'] * 1.15);
                        if ($d >= 1.0) continue;
                        $lip = (1.0 - $d * $d);
                        $lip *= $lip * (0.85 + 0.15 * sin($phase * 2.0 + $seed));
                        $pr += $pal['core'][0] * $lip * 0.85;
                        $pg += $pal['core'][1] * $lip * 0.85;
                        $pb += $pal['core'][2] * $lip * 0.85;
                        $pa = max($pa, $lip * 0.85);
                        continue;
                    }

                    // Tapered jet width + slight flutter near tail
                    $wobble = sin($t * 8.0 - $phase * 2.0 + $seed) * $nz['r'] * 0.12 * $t;
                    $halfW = $nz['r'] * (1.0 - 0.72 * pow(min(1.0, $t), 0.85));
                    if ($halfW < 0.5) continue;

                    $u = abs($across - $wobble) / $halfW;
                    if ($u > 2.4) continue;

                    // Outer soft ion sheath + inner hot plasma core + travelling Mach-diamond rings
                    $axialFade = max(0.0, 1.0 - pow($t, 1.35));
                    $mach = 0.82 + 0.24 * sin($t * 16.0 - $phase * 3.0 + $seed);
                    $sheath = exp(-($u * $u) * 0.85) * $axialFade * $mach;
                    $core = exp(-($u * $u) * 3.8) * max(0.0, 1.0 - pow($t * 1.25, 1.2)) * (0.88 + 0.18 * sin($phase * 3.0 + $seed));

                    $alpha = min(1.0, $sheath * 0.82 + $core * 0.95);
                    if ($alpha < 0.015) continue;

                    // Color blend: outer -> mid -> white-hot core
                    $mixCore = min(1.0, $core * 1.35);
                    $mixMid = min(1.0, $sheath * 1.1);
                    $cr = $pal['outer'][0] * (1.0 - $mixMid) + $pal['mid'][0] * $mixMid;
                    $cg = $pal['outer'][1] * (1.0 - $mixMid) + $pal['mid'][1] * $mixMid;
                    $cb = $pal['outer'][2] * (1.0 - $mixMid) + $pal['mid'][2] * $mixMid;

                    $cr = $cr * (1.0 - $mixCore) + $pal['core'][0] * $mixCore;
                    $cg = $cg * (1.0 - $mixCore) + $pal['core'][1] * $mixCore;
                    $cb = $cb * (1.0 - $mixCore) + $pal['core'][2] * $mixCore;

                    $pr += $cr * $alpha;
                    $pg += $cg * $alpha;
                    $pb += $cb * $alpha;
                    $pa = min(1.0, $pa + $alpha * (1.0 - $pa * 0.5));
                }

                if ($shipAlpha < 0.01 && $pa < 0.015) {
                    continue;
                }

                if ($pa > 0.015) {
                    $pr = min(255.0, $pr / max(0.01, $pa));
                    $pg = min(255.0, $pg / max(0.01, $pa));
                    $pb = min(255.0, $pb / max(0.01, $pa));
                }

                if ($layer === 'front') {
                    // Plume jets in front of visible rear bells (additive-like screen blend over hull)
                    if ($shipAlpha > 0.05) {
                        $outA = max($shipAlpha, $pa);
                        $outR = min(255.0, $sr + $pr * $pa * 0.95);
                        $outG = min(255.0, $sg + $pg * $pa * 0.95);
                        $outB = min(255.0, $sb + $pb * $pa * 0.95);
                    } else {
                        $outA = $pa;
                        $outR = $pr;
                        $outG = $pg;
                        $outB = $pb;
                    }
                } else {
                    // Plume emerges from behind hull silhouette, plus subtle rim bounce on hull edge
                    if ($shipAlpha > 0.5) {
                        $outA = $shipAlpha;
                        $rim = $pa * 0.28;
                        $outR = min(255.0, $sr + $pr * $rim);
                        $outG = min(255.0, $sg + $pg * $rim);
                        $outB = min(255.0, $sb + $pb * $rim);
                    } else {
                        $outA = min(1.0, $shipAlpha + $pa * (1.0 - $shipAlpha));
                        $outR = ($sr * $shipAlpha + $pr * $pa * (1.0 - $shipAlpha)) / max(0.01, $outA);
                        $outG = ($sg * $shipAlpha + $pg * $pa * (1.0 - $shipAlpha)) / max(0.01, $outA);
                        $outB = ($sb * $shipAlpha + $pb * $pa * (1.0 - $shipAlpha)) / max(0.01, $outA);
                    }
                }

                $gdA = (int)max(0, min(127, round((1.0 - $outA) * 127.0)));
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, clampByte($outR), clampByte($outG), clampByte($outB), $gdA));
            }
        }

        imagepng($im, sprintf('%s/f_%02d.png', $tmpDir, $f));
        imagedestroy($im);
    }

    runCmd(sprintf(
        'ffmpeg -y -v error -framerate %d -i %s -c:v libwebp_anim -loop 0 -lossless 0 -compression_level 6 -q:v %d -pix_fmt yuva420p %s',
        $fps,
        escapeshellarg("{$tmpDir}/f_%02d.png"),
        $quality,
        escapeshellarg($outPath)
    ));
    $kb = round(filesize($outPath) / 1024, 1);
    echo "  Plume animated WebP: {$outPath} ({$kb} KB)\n";
    removeDir($tmpDir);
}

/* =========================== COMPARE =========================== */

function cmdCompare(array $opts, string $repoRoot): void
{
    $ids = parseList(requireOpt($opts, 'ids'));
    $newDir = requireOpt($opts, 'new');
    $outPath = requireOpt($opts, 'out');
    $origDir = is_string($opts['orig-dir'] ?? null) ? $opts['orig-dir'] : "{$repoRoot}/styles/theme/nova/gebaeude";
    $hdDir = is_string($opts['hd-dir'] ?? null) ? $opts['hd-dir'] : "{$repoRoot}/styles/theme/nova/gebaeude-hd";
    $plateIds = array_flip(parseList(is_string($opts['plate-ids'] ?? null) ? $opts['plate-ids'] : null));

    ensureDir(dirname($outPath));
    $tmp = sys_get_temp_dir() . '/hn_compare_' . getmypid();
    ensureDir($tmp);

    $hasExistingHd = is_dir($hdDir);
    $W = $hasExistingHd ? 820 : 620;
    $H = 74 + count($ids) * 202;
    $im = imagecreatetruecolor($W, $H);
    $bg = imagecolorallocate($im, 14, 17, 24);
    $border = imagecolorallocate($im, 56, 66, 88);
    $accent = imagecolorallocate($im, 108, 214, 145);
    $warn = imagecolorallocate($im, 245, 130, 110);
    $white = imagecolorallocate($im, 238, 242, 248);
    $muted = imagecolorallocate($im, 155, 166, 188);

    imagefilledrectangle($im, 0, 0, $W, $H, $bg);
    imagestring($im, 5, 20, 14, 'HD Asset Comparison (Classic | Existing HD | New Output)', $white);
    imagestring($im, 3, 20, 42, 'Classic', $muted);
    $newX = $hasExistingHd ? 404 : 212;
    $smX = $hasExistingHd ? 620 : 428;
    if ($hasExistingHd) {
        imagestring($im, 3, 212, 42, 'Existing HD', $warn);
    }
    imagestring($im, 3, $newX, 42, 'New Output (180px)', $accent);
    imagestring($im, 3, $smX, 42, 'In-game 120px', $accent);

    foreach ($ids as $r => $id) {
        $y = 66 + $r * 202;
        $iy = $y + 18;
        $isPlate = isset($plateIds[$id]);

        $newFile = "{$newDir}/{$id}.webp";
        if (!is_file($newFile)) {
            fwrite(STDERR, "Warning: missing {$newFile}, skipping\n");
            continue;
        }
        $kbNew = round(filesize($newFile) / 1024, 1);
        $label = "ID {$id}  (New: {$kbNew} KB)";

        $origFile = findClassicFile($origDir, $id);
        if ($origFile !== null) {
            $t1 = "{$tmp}/{$id}_o.png";
            runCmd(sprintf(
                'magick %s -coalesce -resize 180x180 -background "#0e121b" -gravity center -extent 180x180 PNG24:%s',
                escapeshellarg($origFile . '[0]'),
                escapeshellarg($t1)
            ));
            $p1 = imagecreatefrompng($t1);
            imagecopy($im, $p1, 20, $iy, 0, 0, 180, 180);
            imagedestroy($p1);
        }
        imagerectangle($im, 20, $iy, 200, $iy + 180, $border);

        if ($hasExistingHd && is_file("{$hdDir}/{$id}.webp")) {
            $hdFile = "{$hdDir}/{$id}.webp";
            $kbOld = round(filesize($hdFile) / 1024, 1);
            $label = "ID {$id}  (Existing: {$kbOld} KB -> New: {$kbNew} KB)";
            $t2 = "{$tmp}/{$id}_h.png";
            renderPreviewTile($hdFile, $t2, 180, $isPlate);
            $p2 = imagecreatefrompng($t2);
            imagecopy($im, $p2, 212, $iy, 0, 0, 180, 180);
            imagedestroy($p2);
            imagerectangle($im, 212, $iy, 392, $iy + 180, $warn);
        }

        $t3 = "{$tmp}/{$id}_n.png";
        $t4 = "{$tmp}/{$id}_s.png";
        renderPreviewTile($newFile, $t3, 180, $isPlate);
        renderPreviewTile($newFile, $t4, 120, $isPlate);

        $p3 = imagecreatefrompng($t3);
        $p4 = imagecreatefrompng($t4);
        imagecopy($im, $p3, $newX, $iy, 0, 0, 180, 180);
        imagerectangle($im, $newX, $iy, $newX + 180, $iy + 180, $accent);
        imagecopy($im, $p4, $smX, $iy + 30, 0, 0, 120, 120);
        imagerectangle($im, $smX, $iy + 30, $smX + 120, $iy + 150, $accent);
        imagedestroy($p3);
        imagedestroy($p4);

        imagestring($im, 4, 20, $y, $label, $white);
    }

    imagepng($im, $outPath);
    imagedestroy($im);
    removeDir($tmp);
    echo "Wrote {$outPath}\n";
}

function renderPreviewTile(string $src, string $dst, int $size, bool $isPlate): void
{
    if ($isPlate) {
        runCmd(sprintf(
            'magick %s -resize %dx%d^ -gravity center -extent %dx%d PNG24:%s',
            escapeshellarg($src . '[0]'),
            $size, $size, $size, $size,
            escapeshellarg($dst)
        ));
    } else {
        runCmd(sprintf(
            'magick -size %dx%d radial-gradient:"#2c3548-#12151f" \( %s -resize %dx%d \) -gravity center -composite PNG24:%s',
            $size, $size,
            escapeshellarg($src . '[0]'),
            $size, $size,
            escapeshellarg($dst)
        ));
    }
}

/* =========================== GD HELPERS =========================== */

function clampByte(float $v): int
{
    return (int)max(0, min(255, round($v)));
}

function makePlateBg(int $size): GdImage
{
    $bg = imagecreatetruecolor($size, $size);
    $cx = $size * 0.5;
    $cy = $size * 0.7;
    $maxR = $size * 0.75;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $d = min(1.0, hypot($x - $cx, $y - $cy) / $maxR);
            if ($d < 0.62) {
                $t = $d / 0.62;
                $r = 44 + (26 - 44) * $t;
                $g = 53 + (31 - 53) * $t;
                $b = 72 + (46 - 72) * $t;
            } else {
                $t = ($d - 0.62) / 0.38;
                $r = 26 + (18 - 26) * $t;
                $g = 31 + (21 - 31) * $t;
                $b = 46 + (31 - 46) * $t;
            }
            imagesetpixel($bg, $x, $y, imagecolorallocate($bg, (int)$r, (int)$g, (int)$b));
        }
    }
    return $bg;
}

function loadPngPixels(string $path): array
{
    $im = imagecreatefrompng($path);
    $w = imagesx($im);
    $h = imagesy($im);
    $px = [];
    for ($y = 0; $y < $h; $y++) {
        $row = [];
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($im, $x, $y);
            $a = ($rgba >> 24) & 0x7F;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            $row[] = [$r, $g, $b, $a];
        }
        $px[] = $row;
    }
    imagedestroy($im);
    return [$w, $h, $px];
}

function saveFramePng(int $w, int $h, array $px, string $path): void
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $w, $h, $trans);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = $px[$y][$x];
            if ($a >= 120) {
                continue;
            }
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, clampByte($r), clampByte($g), clampByte($b), $a));
        }
    }
    imagepng($im, $path);
    imagedestroy($im);
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $p = "{$dir}/{$entry}";
        if (is_dir($p)) {
            removeDir($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}
