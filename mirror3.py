import numpy as np
from PIL import Image,ImageDraw
import random

def apply_random_effect(img_array):
    sepia_filter = np.array([[0.393, 0.769, 0.189],
                             [0.349, 0.686, 0.168],
                             [0.272, 0.534, 0.131]])
    img_array[..., :3] = np.dot(img_array[..., :3], sepia_filter.T).clip(0, 255)

    return img_array


def create_mirror_effect(image_path, output_path, iterations, *, scale_factor=0.9, modify=True):
    
    img = Image.open(image_path).convert("RGBA")
    width, height = img.size
    img_array = np.array(img)  # Convert to NumPy array for efficiency
    new_img_array = None

    for i in range(iterations):
        width = int(width * scale_factor)
        height = int(height * scale_factor)

        if width < 1 or height < 1:
            break

        resized_array = np.array(img.resize((width, height), Image.LANCZOS))

        if modify:
            resized_array = apply_random_effect(resized_array)

        if new_img_array is None:
            new_img_array = resized_array.copy()

        modify = not modify

        x = (new_img_array.shape[1] - width) // 2
        y = (new_img_array.shape[0] - height) // 2

        # Composite with alpha blending
        alpha = resized_array[..., 3:] / 255.0
        for c in range(3):
            new_img_array[y:y+height, x:x+width, c] = (
                resized_array[..., c] * alpha[:, :, 0] + new_img_array[y:y+height, x:x+width, c] * (1 - alpha[:, :, 0])
            )

        new_img_array[y:y+height, x:x+width, 3] = 255  # Ensure full opacity

    # Add black square
    #new_img_array[y:y+height, x:x+width, :3] = 0
    #new_img_array[y:y+height, x:x+width, 3] = 255

#    black_overlay = Image.new("RGBA", (new_img_array.shape[1], new_img_array.shape[0]), (0, 0, 0, 0))
    black_overlay = Image.open(image_path).convert("RGBA")

    # Resize it to match new_img_array dimensions
    black_overlay = black_overlay.resize((new_img_array.shape[1], new_img_array.shape[0]))

#    draw = ImageDraw.Draw(black_overlay)
#    corner_radius = min(width, height) // 2  # Set corner radius
#    draw.rounded_rectangle([x, y, x + width, y + height], radius=corner_radius, fill=(0, 0, 0, 255))
    
#    black_overlay_array = np.array(black_overlay)
#    alpha_black = black_overlay_array[..., 3:] / 255.0
#    for c in range(3):
#        new_img_array[..., c] = (
#            black_overlay_array[..., c] * alpha_black[:, :, 0] + new_img_array[..., c] * (1 - alpha_black[:, :, 0])
#        )
#    new_img_array[..., 3] = 255
    

    # Save result
    new_img = Image.fromarray(new_img_array.astype(np.uint8))
    new_img.save(output_path, "PNG")

# Example usage
j=1
mod=True
for i in range(50, 0, -1):
    print(f"frame {j}")
    create_mirror_effect("frames/1024x576_0.jpg", f"frames/{j}.png", i, modify=mod)
    j = j+1
    mod = not mod
