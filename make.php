<?php
shell_exec("rm frames/*.jpg");
function generateMaze($width, $height) {
    $maze = [];
    // Generate the outer walls
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($x == 0 || $x == $width - 1 || $y == 0 || $y == $height - 1) {
                $maze[$y][$x] = 1;
            } else {
                // Randomly place walls and open spaces
                $maze[$y][$x] = rand(0, 1);
            }
        }
    }

    // Ensure there's at least one open space for the player to start
    do {
        $playerX = rand(1, $width - 2);
        $playerY = rand(1, $height - 2);
        // Check for open corridors in the initial direction
        $validStart = false;
        $directions = [
            [1, 0],   // Right
            [-1, 0],  // Left
            [0, 1],   // Down
            [0, -1]   // Up
        ];
        foreach ($directions as $dir) {
            $dx = $dir[0];
            $dy = $dir[1];
            if ($maze[$playerY + $dy][$playerX + $dx] == 0 &&
                $maze[$playerY + 2 * $dy][$playerX + 2 * $dx] == 0) {
                $validStart = true;
                $playerDir = atan2($dy, $dx);
                break;
            }
        }
    } while ($maze[$playerY][$playerX] != 0 || !$validStart);

    return [$maze, $playerX + 0.5, $playerY + 0.5, $playerDir];
}


function generatePath($maze, $startX, $startY) {
    $width = count($maze[0]);
    $height = count($maze);
    $stack = [[$startX, $startY, 0]]; // x, y, direction (0: right, 1: left, 2: down, 3: up)
    $visited = array_fill(0, $height, array_fill(0, $width, false));
    $path = [];

    $dirVectors = [
        [1, 0],   // Right
        [-1, 0],  // Left
        [0, 1],   // Down
        [0, -1]   // Up
    ];

    while (!empty($stack)) {
        list($x, $y, $dir) = array_pop($stack);

        if ($visited[$y][$x]) continue;
        $visited[$y][$x] = true;
        $path[] = [$x, $y, $dir];

        // Add neighboring cells to stack
        for ($i = 0; $i < 4; $i++) {
            $newX = $x + $dirVectors[$i][0];
            $newY = $y + $dirVectors[$i][1];
            if ($newX >= 0 && $newX < $width && $newY >= 0 && $newY < $height && !$visited[$newY][$newX] && $maze[$newY][$newX] == 0) {
                $stack[] = [$newX, $newY, $i];
            }
        }
    }

    return $path;
}

// Usage
list($maze, $playerX, $playerY, $playerDir) = generateMaze(30, 30);

for($i=1;$i<=28;$i++){
	for($j=1;$j<=28;$j++){
		$maze[$i][$j] = $i==10 ? 0 : $maze[$i][$j];
	}

}


// Load textures
$files = glob("1400x1400/*.jpg");
shuffle($files);
$wallImage = imagecreatefromjpeg($files[0]);
$groundImage = imagecreatefromjpeg($files[0]);
$ceilingImage = imagecreatefromjpeg($files[mt_rand(0,1)]);


// Image dimensions
$imageWidth = 1200;
$imageHeight = 1200;

$path = generatePath($maze, $playerX, $playerY);

$path = array_map(fn($x) => [$x+.5, 10.5], range(1, 28));
$playerDir = -.5;
$inc = .1;
$playerX = [1, 10];

$rotate = false;
foreach($path as $i => $coords){

	if($i === 29) break;
	echo "Rendering frame $i\n";

	list($playerX, $playerY) = $coords;

	if(mt_rand(0,1))
//	$playerDir += $inc;

	if($playerDir > .5) $inc = -.1;
	elseif($playerDir < -.5) $inc = +.1;
	


	// Create a base image
	$canvas = imagecreatetruecolor($imageWidth, $imageHeight);

	// Fill the ground and ceiling
	$groundHeight = $imageHeight / 2;
	$ceilingHeight = $imageHeight / 2;
//	imagecopyresampled($canvas, $groundImage, 0, $groundHeight, 0, 0, $imageWidth, $groundHeight, imagesx($groundImage), imagesy($groundImage));
//	imagecopyresampled($canvas, $ceilingImage, 0, 0, 0, 0, $imageWidth, $ceilingHeight, imagesx($ceilingImage), imagesy($ceilingImage));

	// Raycasting
	for ($x = 0; $x < $imageWidth; $x++) {
	    // Calculate ray position and direction
	    $cameraX = 2 * $x / $imageWidth - 1;
	    $rayDirX = cos($playerDir) + $cameraX * sin($playerDir);
	    $rayDirY = sin($playerDir) - $cameraX * cos($playerDir);

	    // Which box of the map we're in
	    $mapX = (int)$playerX;
	    $mapY = (int)$playerY;

	    // Length of ray from current position to next x or y side
	    $sideDistX = ($rayDirX < 0) ? ($playerX - $mapX) * abs(1 / $rayDirX) : ($mapX + 1.0 - $playerX) * abs(1 / $rayDirX);
	    $sideDistY = ($rayDirY < 0) ? ($playerY - $mapY) * abs(1 / $rayDirY) : ($mapY + 1.0 - $playerY) * abs(1 / $rayDirY);

	    // Length of ray from one x or y side to next x or y side
	    $deltaDistX = abs(1 / $rayDirX);
	    $deltaDistY = abs(1 / $rayDirY);

	    // What direction to step in x or y direction (either +1 or -1)
	    $stepX = ($rayDirX < 0) ? -1 : 1;
	    $stepY = ($rayDirY < 0) ? -1 : 1;

	    $hit = 0; // Was there a wall hit?
	    $side = 0; // Was a NS or a EW wall hit?

	    // Perform DDA
	    while ($hit == 0) {
	        // Jump to next map square, OR in x-direction, OR in y-direction
	        if ($sideDistX < $sideDistY) {
	            $sideDistX += $deltaDistX;
	            $mapX += $stepX;
	            $side = 0;
	        } else {
	            $sideDistY += $deltaDistY;
	            $mapY += $stepY;
	            $side = 1;
	        }
	        // Check if ray has hit a wall
	        if ($maze[$mapY][$mapX] > 0) $hit = 1;
	    }

	    // Calculate distance projected on camera direction
	    if ($side == 0) {
	        $perpWallDist = ($mapX - $playerX + (1 - $stepX) / 2) / $rayDirX;
	    } else {
	        $perpWallDist = ($mapY - $playerY + (1 - $stepY) / 2) / $rayDirY;
	    }

	    // Calculate height of line to draw on screen
	    $lineHeight = (int)($imageHeight / $perpWallDist);

	    // Calculate lowest and highest pixel to fill in current stripe
	    $drawStart = -$lineHeight / 2 + $imageHeight / 2;
	    if ($drawStart < 0) $drawStart = 0;
	    $drawEnd = $lineHeight / 2 + $imageHeight / 2;
	    if ($drawEnd >= $imageHeight) $drawEnd = $imageHeight - 1;

	    // Calculate which x-coordinate of the texture to use
	    $wallX = ($side == 0) ? $playerY + $perpWallDist * $rayDirY : $playerX + $perpWallDist * $rayDirX;
	    $wallX -= floor($wallX);

	    // x-coordinate on the texture
	    $texX = (int)($wallX * imagesx($wallImage));
	    if ($side == 0 && $rayDirX > 0) $texX = imagesx($wallImage) - $texX - 1;
	    if ($side == 1 && $rayDirY < 0) $texX = imagesx($wallImage) - $texX - 1;

	    // Draw the pixels of the stripe as a vertical line
	    for ($y = $drawStart; $y < $drawEnd; $y++) {
	        // Calculate y-coordinate on the texture
	        $d = $y * 256 - $imageHeight * 128 + $lineHeight * 128;
	        $texY = (($d * imagesy($wallImage)) / $lineHeight) / 256;
	        // Get the color from the texture
	        $color = imagecolorat($wallImage, $texX, $texY);
	        if ($side == 1) {
	            // Make color darker for y-sides
	            $r = ($color >> 16) & 0xFF;
	            $g = ($color >> 8) & 0xFF;
	            $b = $color & 0xFF;
	            $r = (int)($r / 1.5);
	            $g = (int)($g / 1.5);
	            $b = (int)($b / 1.5);
	            $color = imagecolorallocate($canvas, $r, $g, $b);
	        }
	        imagesetpixel($canvas, $x, $y, $color);
	    }
	}

	$canvas = imagerotate($canvas, $i * 5, null);
	
	// Output the result
	imagejpeg($canvas,'frames/'.$i.'.jpg');

	// Free up memory
	imagedestroy($wallImage);
	imagedestroy($groundImage);
	imagedestroy($ceilingImage);
	imagedestroy($canvas);

}



?>
