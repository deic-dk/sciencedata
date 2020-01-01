## Data service

- built on ownCloud-7 with backporting of Nextcloud features
- using apps and a highly customized theme, i.e. practically not touching the ownCloud core
- the core apps are interdependent, and should be installed all together
- moreover, they should be accompanied by the theme

Core apps:

- [user_saml](https://github.com/deic-dk/user_saml)
- [chooser](https://github.com/deic-dk/chooser)
- [files_sharding](https://github.com/deic-dk/files_sharding)
- [user_group_admin](https://github.com/deic-dk/user_group_admin)
- [files_accounting](https://github.com/deic-dk/files_accounting)
- [user_notification](https://github.com/deic-dk/user_notification)
- [menu_fonticons](https://github.com/deic-dk/menu_fonticons)
- [internal_bookmarks](https://github.com/deic-dk/internal_bookmarks)

Theme:

- [deic_oc7_theme](https://github.com/deic-dk/deic_oc7_theme)

If you want to go ahead and create a similar service, you need to install all of these apps together
and modify the theme to match your organization look and feel and other requirements.

