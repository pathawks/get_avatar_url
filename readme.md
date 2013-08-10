# get_avatar_url()

This plugin adds the `get_avatar_url` function to WordPress.

It is an implimentation of [Michael Adams՚ patch](http://core.trac.wordpress.org/ticket/21195#comment:12) from [ticket #21195](http://core.trac.wordpress.org/ticket/21195)


## What does it do?

  * Get avatar by user_id, user (new), email address, MD5 hash (new), comment, or post (new).
    * Getting by MD5 is handy when you have a hash but no the email address.
    * Getting by user or post is not strictly necessary, but provides a little extra context to plugins hooking in to various hooks.
  * Support for all Gravatar parameters:
    * size
    * default (404, retro, monsterid, wavatar, identicon, mysteryman, blank, gravatar logo, and URL)
    * force_default
    * rating
  * pre_get_avatar_url, get_avatar_url, pre_get_avatar, get_avatar filters (see [#21930][102])
    * The first three of which can modify the `$args` parameter on the fly (it's passed by reference to the filter) allowing plugins to modify default behavior without needing to duplicate code.
  * Uses `set_url_scheme()`
  * Updates to latest Gravatar supported subdomains.
  * Argument to add classes to the returned img element.
  * Argument to force the return of the img element, ignoring the show_avatars option.
  * Fixes some edge case bugs
    * Non integer and non-positive sizes
    * Negative user IDs
    * Comments by users who's accounts have since been deleted

## Implementation Notes

With the patched filters, I don't think `get_avatar_url()` needs to be pluggable.

Both functions now accept a single `$args` array parameter instead of many parameters. This makes it easier for plugins to extend the functionality or replace it with a service that doesn't have the same set of parameters.

In the attached patch, `get_avatar()` respects the show_avatars option (as it does currently) unless the new force_display argument is true. In contrast, `get_avatar_url()` ignores the show_avatars option and always returns a URL if it can.

If the default avatar is a URL, `get_avatar()` currently appends the requested size to the default URL as an `s={$size`} query string parameter. In the patch, this behavior is removed since such a parameter is not needed (and is ignored) by Gravatar when serving default images. In theory, that removal could break some avatar-substituting plugins. I'm ambivalent: removing it cleans up the code a bit, but we can add it back.

If `get_avatar()` can't figure out an email address to hash, it currently uses `unknown@gravatar.com`, yielding URLs like: `http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96`. The attached patch instead does not include any hash in the URL for these cases: `http://www.gravatar.com/avatar/?s=96`.

If `get_avatar()` can't figure out an email address to hash but has a default image URL, it currently uses that URL as the src of the img: `src="http://example.com/default.jpg"`. The attached patch instead sends that URL through Gravatar so that it can be sized correctly: `src="http://www.gravatar.com/avatar/?s=96&amp;d=http://example.com/default.jpg"`, which could break some URLs (since the provided default URL must be accessible to Gravatar for the image to be served). I chose this behavior since it matches what happens when we can find an email address to hash, but when that hash has no associated Gravatar.

Getting a URL by MD5 as implemented requires you to pass "{$md5}@md5.gravatar.com" in the first parameter. I did this because some hashes will pass the `is_numeric()` test. Who can say? Maybe someday a WordPress site will have more than 10 nonillion user accounts :)

In addition to the URL returned by `get_avatar_url()`, `get_avatar()` needs some other information calculated by `get_avatar_url()`: the sanitized avatar size, whether a potential avatar match was found, and potentially more by/for the various filters. Rather than calculating things twice or adding an intermediary function, `get_avatar_url()`'s args array accepts a 'processed_args' item, which should be passed by reference. `get_avatar_url()` fills that reference with the processed args/flags, and `get_avatar()` reads it.

I chose to implement this "out variable" as `$args['processed_args']` rather than `get_avatar_url( $id_or_email, $args, &amp;$processed_args )` for selfish reasons: versions of Jetpack have shipped with code like:

    get_avatar_url( 1, 96, '' );

which will generate a Fatal Error if `get_avatar_url()`'s signature has a reference as the third parameter.  


## License

WordPress (and this plugin) is free software, and is released under the terms of the **GPL version 2** or (at your option) any later version. See `license.txt`.
