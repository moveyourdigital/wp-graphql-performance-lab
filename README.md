# WPGraphQL Performance Lab
This plugin extends the [WPGraphQL](wp-graphql/wp-graphql) plugin to provide support for the [Performance Plugin](WordPress/performance), tipically the possibility to retrieve WebP versions of `srcSet` in `MediaItem` and the dominant color.

### Requirements
- PHP >= 7.2
- WordPress >= 5.8

### Installation
1. Install the [Performance Plugin](WordPress/performance) and [WPGraphQL](wp-graphql/wp-graphql)
2. Upload the zip of this plugin to your WordPress plugins
3. Activate all plugins

### Usage
After installation, you can try the following query:
```graphql
query GetMediaItem($id: ID!) {
  mediaItem(id: $id) {
    sourceUrl
    srcSet(variant: WEBP)
    dominantColor(format: HEX)
  }
}
```
The result being, for instance:
```json
{
  "data": {
    "mediaItem": {
      "sourceUrl": "https://example.com/uploads/2022/06/podcats.jpg",
      "dominantColor": "#44b4cb",
      "srcSet": "https://example.com/uploads/2022/06/podcats-450x160.webp 450w, https://example.com/uploads/2022/06/podcats-1024x364.webp 1024w, https://example.com/uploads/2022/06/podcats-768x273.webp 768w, https://example.com/uploads/2022/06/podcats.webp 1440w"
    }
  }
}
```

### Advanced use-cases
A couple of receipes for advances use-cases.

#### Return both jpeg and webp `srcSet`

Duplicate `srcSet` by using a different alias key:
```graphql
query GetMediaItem($id: ID!) {
  mediaItem(id: $id) {
    sourceUrl
    srcSet(variant: ORIGINAL)
    webPSrcSet: srcSet(variant: WEBP)
  }
}
```
Resulting in something like this:
```json
{
  "data": {
    "mediaItem": {
      "sourceUrl": "https://example.com/uploads/2022/06/podcats.jpg",
      "srcSet": "https://example.com/uploads/2022/06/podcats-450x160.jpg 450w, https://example.com/uploads/2022/06/podcats-1024x364.jpg 1024w, https://example.com/uploads/2022/06/podcats-768x273.jpg 768w, https://example.com/uploads/2022/06/podcats.jpg 1440w",
      "webPSrcSet": "https://example.com/uploads/2022/06/podcats-450x160.webp 450w, https://example.com/uploads/2022/06/podcats-1024x364.webp 1024w, https://example.com/uploads/2022/06/podcats-768x273.webp 768w, https://example.com/uploads/2022/06/podcats.webp 1440w"
    }
  }
}
```
