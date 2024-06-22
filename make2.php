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
   $wallTexture = imagecreatefromjpeg($files[0]);
    $floorTexture = imagecreatefromjpeg($files[0]);
    $ceilingTexture = imagecreatefromjpeg($files[mt_rand(0,1)]);
 

// Image dimensions
$imageWidth = 1200;
$imageHeight = 800;

$path = generatePath($maze, $playerX, $playerY);

$path = array_map(fn($x) => [$x+.5, 10.5], range(1, 28));
$playerDir = -.6;
$playerX = [1, 10];

$rotate = false;
$inc = .1;
foreach($path as $i => $coords){

	if($i === 29) break;
	echo "Rendering frame $i\n";

	list($playerX, $playerY) = $coords;

	if(mt_rand(0,1))
	$playerDir += $inc;

	if($playerDir > .5) $inc = -.1;
	elseif($playerDir < -.5) $inc = +.1;
	

	echo $playerDir."\n";

    $width = count($maze[0]);
    $height = count($maze);
   
    $textureWidth = imagesx($wallTexture);
    $textureHeight = imagesy($wallTexture);

    $screenWidth = 640;
    $screenHeight = 480;
    $image = imagecreatetruecolor($screenWidth, $screenHeight);

    for ($x = 0; $x < $screenWidth; $x++) {
        $cameraX = 2 * $x / $screenWidth - 1;
        $rayDirX = cos($playerDir) + cos($playerDir + M_PI / 2) * $cameraX;
        $rayDirY = sin($playerDir) + sin($playerDir + M_PI / 2) * $cameraX;

        $mapX = (int)$playerX;
        $mapY = (int)$playerY;

        $deltaDistX = abs(1 / $rayDirX);
        $deltaDistY = abs(1 / $rayDirY);

        if ($rayDirX < 0) {
            $stepX = -1;
            $sideDistX = ($playerX - $mapX) * $deltaDistX;
        } else {
            $stepX = 1;
            $sideDistX = ($mapX + 1.0 - $playerX) * $deltaDistX;
        }

        if ($rayDirY < 0) {
            $stepY = -1;
            $sideDistY = ($playerY - $mapY) * $deltaDistY;
        } else {
            $stepY = 1;
            $sideDistY = ($mapY + 1.0 - $playerY) * $deltaDistY;
        }

        $hit = 0;
        while ($hit == 0) {
            if ($sideDistX < $sideDistY) {
                $sideDistX += $deltaDistX;
                $mapX += $stepX;
                $side = 0;
            } else {
                $sideDistY += $deltaDistY;
                $mapY += $stepY;
                $side = 1;
            }
            if ($maze[$mapY][$mapX] > 0) $hit = 1;
        }

        if ($side == 0) {
            $perpWallDist = ($mapX - $playerX + (1 - $stepX) / 2) / $rayDirX;
        } else {
            $perpWallDist = ($mapY - $playerY + (1 - $stepY) / 2) / $rayDirY;
        }

        $lineHeight = (int)($screenHeight / $perpWallDist);

        $drawStart = -$lineHeight / 2 + $screenHeight / 2;
        if ($drawStart < 0) $drawStart = 0;

        $drawEnd = $lineHeight / 2 + $screenHeight / 2;
        if ($drawEnd >= $screenHeight) $drawEnd = $screenHeight - 1;

        $texX = (int)($textureWidth * (($side == 0) ? ($playerY + $perpWallDist * $rayDirY) : ($playerX + $perpWallDist * $rayDirX)));
        if ($side == 0 && $rayDirX > 0) $texX = $textureWidth - $texX - 1;
        if ($side == 1 && $rayDirY < 0) $texX = $textureWidth - $texX - 1;

        for ($y = 0; $y < $screenHeight; $y++) {
            if ($y < $drawStart) {
                $distance = ($screenHeight / (2.0 * $y - $screenHeight));
                $floorX = $playerX + $distance * $rayDirX;
                $floorY = $playerY + $distance * $rayDirY;

                $texFloorX = (int)($textureWidth * ($floorX - floor($floorX))) % $textureWidth;
                $texFloorY = (int)($textureHeight * ($floorY - floor($floorY))) % $textureHeight;

                if ($texFloorX < 0) $texFloorX += $textureWidth;
                if ($texFloorY < 0) $texFloorY += $textureHeight;

                $color = imagecolorat($ceilingTexture, $texFloorX, $texFloorY);
                imagesetpixel($image, $x, $y, $color);
            } elseif ($y > $drawEnd) {
                $distance = ($screenHeight / (2.0 * ($screenHeight - $y) - $screenHeight));
                $floorX = $playerX + $distance * $rayDirX;
                $floorY = $playerY + $distance * $rayDirY;

                $texFloorX = (int)($textureWidth * ($floorX - floor($floorX))) % $textureWidth;
                $texFloorY = (int)($textureHeight * ($floorY - floor($floorY))) % $textureHeight;

                if ($texFloorX < 0) $texFloorX += $textureWidth;
                if ($texFloorY < 0) $texFloorY += $textureHeight;

                $color = imagecolorat($floorTexture, $texFloorX, $texFloorY);
                imagesetpixel($image, $x, $y, $color);
            } else {
            }
        }
    }



 
	// Output the result
	imagejpeg($image,'frames/'.$i.'.jpg');
 
    imagedestroy($image);
    imagedestroy($wallTexture);
    imagedestroy($floorTexture);
    imagedestroy($ceilingTexture);


    imagedestroy($image);
    imagedestroy($wallTexture);
    imagedestroy($floorTexture);
    imagedestroy($ceilingTexture);



}



?>
