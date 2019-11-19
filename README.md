# dcs-wordpress-plugin

`dcs-wordpress-plugin` allows to integrate your WordPress blog with your
Discourse forum. It is part of [Docuss](https://github.com/sylque/docuss), a set
of tools to integrate websites and web apps with Discourse.

## Plugin Installation

1. Download this repository and copy the `docuss` folder to your Wordpress
   `plugins` folder (the full path is often
   `/var/www/wordpress/wp-content/plugins/`).

2. Set the Owner and Group of the `docuss` folder to the same values as the
   other Wordpress files.

   For example, if you run `ls -l` in the `plugins` folder and see this:

   ```
   drwxr-xr-x 3 root     root     4096 Aug 14 17:30 docuss
   -rw-r--r-- 1 www-data www-data   28 Jun  5  2014 index.php
   ```

   then run `chown www-data:www-data docuss/` so that it looks like this:

   ```
   drwxr-xr-x 3 www-data www-data 4096 Aug 14 17:30 docuss
   -rw-r--r-- 1 www-data www-data   28 Jun  5  2014 index.php
   ```

## How To Use

1. Optional: in the plugin folder, edit `dcs-website.json` to define where and
   how you want comments/discussions to be added to your blog (see the
   documentation [here](https://github.com/sylque/dcs-website-schema)).

2. **Activate the plugin** (and do this again each time you modify
   `dcs-website.json`).

3. Check that the generated `json` file is accessible at this url:

   ```
   http://your.wordpress.blog/wp-content/plugins/docuss/dcs-website.php
   ```

4. Install the [Docuss plugin](https://github.com/sylque/dcs-discourse-plugin)
   in your Discourse instance and set it up with the above url.

## License

See [here](https://github.com/sylque/docuss#license).
