## Data service

- built on ownCloud with backporting of Nextcloud features
- using apps and a highly customized theme, i.e. not touching the ownCloud core
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

If you want to go ahead and create a similar service, you need to install all of these apps
and modify the theme to match your organization look and feel and other requirements.
You're welcome to get in touch.

## Compute service

- built on Kubernetes
- user frontend in the form of an ownCloud app
- users can choose from a library of predefined manifests - including manifests for running Jupyter
- users can add manifests/images to this library

Repositories:

- [user_pods](https://github.com/deic-dk/user_pods)
- [pod_manifests](https://github.com/deic-dk/pod_manifests)
- [sciencedata_images](https://github.com/deic-dk/sciencedata_images)
- [jupyter_sciencedata](https://github.com/deic-dk/jupyter_sciencedata)
- [sciencedata_kubernetes](https://github.com/deic-dk/sciencedata_kubernetes)
