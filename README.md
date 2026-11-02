# Batch Rename Files or Parts of File Names in an Online Folder

**Made for you if you have a folder on your website where every file name, or part of every file name, needs to change, all at once.**

One PHP file (`rename-files.php`) — no install, no database, no command line.

Built by Geo Geller with Claude, a non-human child of Anthropic. Free to use.

---

## How it began

An artist had a folder of sumi paintings on his website, every file still carrying a camera name like `A_Hint_of_Something_IMG_2600.jpg`. He wanted the titles back. One morning, in conversation with Claude, the fix grew into this tool.

```
A_Hint_of_Something_IMG_2600.jpg   →   A_Hint_of_Something.jpg
Blue_Moon_IMG_0042-2.jpg           →   Blue_Moon.jpg
```

## What it does

- **Find and delete** any text in file names, or **replace** it with something else
- **Wild card:** `_IMG_*` removes `_IMG_2600`, `_IMG_0042-2`, `_IMG_A7` … and always stops at the extension
- **Add to the end** of every name, e.g. `_by_your_name`
- **Choose file types** by checkbox: images, audio, video, pages, documents, or any extension you type
- **Preview** every change before anything happens, with the removed part shown in red
- **Undo** the last batch, or the one before that
- **Password protected** and works on any host running PHP 7.4 or newer

## Who it's for

- **Photographers and artists** — strip camera names (IMG_2600, DSC_0042) so real titles show
- **Archivists and documentarians** — clean up years of footage, audio and scans in one pass
- **Musicians and podcasters** — tidy .wav and .mp3 libraries, or add a credit to every file
- **Anyone on shared hosting** (IONOS, GoDaddy, Bluehost…) who can upload files but has no command line
- **Teachers and small nonprofits** — plain English, Preview and Undo, nothing to be afraid of
- **Web makers and tinkerers** — one readable file, no dependencies, easy to learn from and adapt

## Quick start

1. Download `rename-files.php`.
2. Open it in a plain text editor and change this line near the top to your own password:
   ```php
   const TOOL_PASSWORD = 'change-this-password';
   ```
3. Upload it to your site's main folder (`public_html`, `htdocs` or `www`).
4. Visit `https://your-site.com/rename-files.php` and log in.
5. Pick a folder → type what to find → **Preview** → **Rename**.
6. When you're done, delete the file from your server.

Full step-by-step instructions are in **[READ-ME-FIRST.txt](READ-ME-FIRST.txt)**.

## What it will never do

- Rename scripts or server files (`.php`, `.htaccess`, `.js`, `.css`, `.json`, `.ini` and more), even if asked
- Rename hidden files, or itself
- Reach outside the folder it was uploaded to
- Overwrite a file — clashes are skipped and shown in red
- Leave a file with no name

---

## About us

I'm Geo Geller, a Mad-Hattan-based recovering photographer, documentary filmmaker, artist, painter, and moving/still/audio image-maker. Over the decades I've gathered a few hundred thousand photographs, documentaries, and my Myth-Illogical Myth-U-Mentaries, to name a few.

To live with all of that, I adapted an online program that reads directories and turns them into players for my music, photos, audio, poetry and videos across my websites. Now I've been developing new creative tools with Claude, a non-human child of Anthropic.

The collaboration grew out of another one still in progress: *The Roadless Traveled with Godot*, a real-life and online conversation with Godot (ChatGPT) about what it's like to be human, among other things. That project is on hold for now, and in full disclosure, these tools are its offspring.

This is the first of a diverse and growing collection of self-contained PHP projects — one file each, no database, no plugins — most of which we release free, for anyone with a website and a folder full of things that deserve better names.

**Random Place** — online since 1996 · [randomplace.com](https://randomplace.com) · geo@randomplace.com

## License

Free under the [MIT License](LICENSE): use it, change it, share it — just keep the credit line.
