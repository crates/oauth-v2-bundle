[![Build Status](https://travis-ci.org/keboola/oauth-v2-bundle.svg?branch=master)](https://travis-ci.org/keboola/oauth-v2-bundle)

## Local development

See [/docker/README.md](/docker/README.md).

## TLDR; samples
Api is registered by calling api call: `POST` https://syrup.keboola.com/oauth-v2/manage . You can test registered api and get sample token data by visiting url https://syrup.keboola.com/oauth-v2/authorize/<COMPONENT_ID> e.g. https://syrup.keboola.com/oauth-v2/authorize/keboola.ex-twitter

### request body samples of POST oauth-v2/manage (register api):
- **keboola.ex-twitter**: auth v 1.0
```
{
  "component_id": "keboola.ex-twitter",
  "friendly_name": "twitter ex",
  "app_key": "XXX",
  "app_secret": "XXX",
  "auth_url": "https://api.twitter.com/oauth/authorize?oauth_token=%%oauth_token%%",
  "request_token_url": "https://api.twitter.com/oauth/request_token",
  "token_url": "https://api.twitter.com/oauth/access_token",
  "oauth_version": "1.0"
}
```

- **keboola.ex-google-drive**: auth v 2.0
```
{
  "component_id": "keboola.ex-google-drive",
  "friendly_name": "Google Drive Extractor",
  "app_key": "XXX.apps.googleusercontent.com",
  "app_secret": "XXX",
  "auth_url": "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&redirect_uri=%%redirect_uri%%&client_id=%%client_id%%&access_type=offline&prompt=consent&scope=https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/spreadsheets.readonly",
  "token_url": "https://www.googleapis.com/oauth2/v4/token",
  "oauth_version": "2.0"
}
```

- **keboola.ex-facebook-ads**: auth v facebook
```
{
  "component_id": "keboola.ex-facebook-ads",
  "friendly_name": "Facebook ads",
  "app_key": "xxx",
  "app_secret": "xxx",
  "oauth_version": "facebook",
  "permissions": "manage_pages, public_profile",
  "graph_api_version": "v2.8"

}
```

## API Documentation

 - [http://docs.oauthv2.apiary.io/](http://docs.oauthv2.apiary.io/)
