## 本地安装

composer.json 文件中添加以下内容:

```json
"repositories": [
    {
        "type": "path",
        "url": "rap", // 指向组件包所在的相对路径
        "options": {
            "symlink": true // 是否创建符号链接 (false 为复制)
        }
    }
]
```

```bash
composer require chaihao/laravel-rap-admin:@dev
```

## 打标签

```
git tag -a v1.4.2 -m "Release version 1.4.2"
```

## 推送到远程仓库

```
git push origin v1.4.2
```
