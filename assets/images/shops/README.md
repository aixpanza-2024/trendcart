# Shop Images Guide

## Required Images

You need to add 6 shop images to this directory with the following filenames:

1. **elegant-fabrics.jpg** - Elegant Fabrics shop
2. **royal-sarees.jpg** - Royal Sarees shop
3. **fashion-hub.jpg** - Fashion Hub shop
4. **traditional-touch.jpg** - Traditional Touch shop
5. **designer-collection.jpg** - Designer Collection shop
6. **cotton-corner.jpg** - Cotton Corner shop

## Image Specifications

- **Dimensions**: 400x250 pixels (recommended)
- **Format**: JPG or JPEG
- **Aspect Ratio**: 16:10 (landscape)
- **File Size**: Keep under 200KB for faster loading
- **Content**: Shop exterior, fabric displays, or relevant store imagery

## Where to Get Images

### Option 1: Free Stock Photos
You can download free images from:

1. **Unsplash** (https://unsplash.com)
   - Search: "fabric store", "textile shop", "saree shop", "fabric market"

2. **Pexels** (https://pexels.com)
   - Search: "clothing store", "fashion boutique", "textile"

3. **Pixabay** (https://pixabay.com)
   - Search: "fabric", "textile", "saree", "dress materials"

### Option 2: Similar Online Shops
You can take inspiration from real fabric/textile shops:

- Wedding Malls
- FabIndia
- Soch
- BIBA
- Meena Bazaar
- Kalaniketan
- RMKV
- Nalli Silks

**Note**: If using images from real businesses, ensure you have permission or use royalty-free alternatives.

### Option 3: Custom Photography
Take your own photos of:
- Local fabric stores
- Textile markets
- Saree shops
- Fabric displays

## How to Add Images

1. Download or capture 6 suitable images
2. Resize them to 400x250 pixels (use any photo editor or online tool like https://www.iloveimg.com/resize-image)
3. Save them with the exact filenames listed above
4. Place them in this directory: `assets/images/shops/`

## Fallback Behavior

The website is configured with fallback placeholder images. If a shop image is not found, it will automatically display a placeholder with the shop name. This means:

- ✅ Website will work even without images
- ✅ You can add images one at a time
- ✅ No broken image icons will appear

## Example File Structure

```
assets/
└── images/
    └── shops/
        ├── elegant-fabrics.jpg     ← Add this
        ├── royal-sarees.jpg        ← Add this
        ├── fashion-hub.jpg         ← Add this
        ├── traditional-touch.jpg   ← Add this
        ├── designer-collection.jpg ← Add this
        ├── cotton-corner.jpg       ← Add this
        └── README.md               (this file)
```

## Tips for Best Results

1. **Consistent Style**: Try to use images with similar lighting and color tones
2. **High Quality**: Use clear, professional-looking images
3. **Relevant Content**: Show fabric displays, store interiors, or textile products
4. **Optimization**: Compress images to reduce file size without losing quality
5. **Test**: After adding images, open index.html in browser to verify they display correctly

---

**Need Help?**
If images don't appear:
1. Check the filename matches exactly (case-sensitive)
2. Ensure the file is in the correct folder
3. Clear browser cache (Ctrl+F5)
4. Check browser console for errors (F12)
