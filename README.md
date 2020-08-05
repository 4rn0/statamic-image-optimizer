# ImageOptimizer

This addon can optimize PNGs, JPGs, and GIFs by running them through various image optimization tools. Just what you needed to get those Google Pagespeed bonus points! 🤘

## ImageOptimizer is a commercial addon
You can use it for free while in development, but it requires a license to use on a live site. Learn more or buy a license on [The Statamic Marketplace](https://statamic.com/marketplace/addons/imageoptimizer-v3)!

## Beta
ImageOptimizer is an addon for Statamic v3, which is still in beta right now. You should be careful to use it in production. There can be breaking changes!  

**Please note** that Statamic v3 beta is currently missing a `GlideImageGenerated` event. So until the fine gentlemen of Wilderborn implement one (or until someone merges my [pull request](https://github.com/statamic/cms/pull/2160) that implements it) optimizing Glide images unfortunately does not work at the moment.