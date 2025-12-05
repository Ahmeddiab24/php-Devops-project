<?php
$dir = "images/"; // مسار فولدر الصور
$images = glob($dir . "*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}", GLOB_BRACE);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>معرض الصور</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      text-align: center;
      padding: 20px;
    }
    h1 {
      color: #333;
      margin-bottom: 30px;
    }
    .gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      justify-content: center;
    }
    .gallery img {
      width: 200px;
      height: 200px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      transition: transform 0.3s;
    }
    .gallery img:hover {
      transform: scale(1.1);
    }
  </style>
</head>
<body>
  <h1>📸 معرض الصور</h1>
  <div class="gallery">
    <?php foreach($images as $img): ?>
      <img src="<?php echo $img; ?>" alt="صورة">
    <?php endforeach; ?>
  </div>
</body>
</html>
