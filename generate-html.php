<?php

/**
 * Class Template
 */
class Template
{
    /** @var string $template */
    public $template = "";

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists(get_class($this), $name)) {
            return $this->$name;
        }

        $method = "get" . ucfirst($name);

        if (!method_exists($this, $method)) {
            throw new Exception("Tried to parse non-existent property [$name] - no getter method [$method] exists.");
        }

        return $this->$method();
    }

    /**
     * @return string
     */
    public function parse() : string
    {
        $template = $this->template;
        $vars = $this->getVarsFromTemplate();

        foreach ($vars as $key) {
            $regex = "/\{\{" . $key . "\}\}/";
            $value = $this->$key;
            $template = preg_replace($regex, $value, $template);
        }
    
        return $template;
    }

    /**
     * @return array
     */
    private function getVarsFromTemplate() : array
    {
        $keys = [];
        $regex = "/\{\{.+?\}\}/";
        $matches = [];
        
        preg_match_all($regex, $this->template, $matches);

        if (!count($matches)) {
            return $keys;
        }

        foreach ($matches[0] as $match) {
            $key = preg_replace("/[\{\}]/", "", $match);
            $keys[] = $key;
        }

        return $keys;
    }
}

/**
 * Class Page
 */
class Page extends Template
{
    /** @var string $template */
    public $template = <<<HTML
<html>
    <head>
        <title>{{title}}</title>
        <style>{{css}}</style>
    </head>
    <body>
        <div>{{chapters}}</div>
        <script>{{js}}</script>
    </body>
</html>
HTML;

    /** @var string $title */
    public $title;

    /**
     * @param string $title
     */
    public function __construct(string $title) {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getChapters() : string
    {
        $directories = array_filter(
            scandir("."),
            function ($dir) {
                return is_dir("./$dir") && !in_array($dir, [".", ".."]);
            }
        );
        $chapters = array_map(
            function ($directory) {
                return (new Chapter($directory))->parse();
            },
            $directories
        );
    
        return implode("", $chapters);
    }

    /**
     * @return string
     */
    public function getCss() : string
    {
        return <<<CSS
* {
    margin: 0;
    padding: 0;
}
.chapter {
    margin:10px;
    padding:15px;
    background-color: #faf7eb;
}
.chapter .videos {
    margin-top: 10px;
}
.chapter .videos .video {
    padding-top: 5px;
    padding-bottom: 5px;
}
.chapter h2 a {
    color:#444;
}
.chapter .videos {
    display:none;
}
.video-player {
    display:none;
}
video {
    width: 100%;
    margin-top:15px;
}
.video-toggle {
    cursor: pointer;
    text-transform: lowercase;
    background-color:#fa6f48;
    color:#fff;
    font-size:0.8em;
    padding:0.2em;
}

CSS;
    }

    /**
     * @return string
     */
    public function getJs() : string
    {
        return <<<JS
(function() {
    function eventTargetHasClass(event, className) {
        return [].slice.call(event.target.classList).includes(className);
    }

    function hideAllChapters() {
        [].slice.call(document.querySelectorAll(".chapter .videos")).forEach(section => section.style.display = "none");
    }

    function hideAllChaptersExcept(chapter) {
        hideAllChapters();
        chapter.querySelector(".videos").style.display = "block";
    }

    function showChapter(chapterLink) {
        const chapter = chapterLink.parentElement.parentElement;
        if (chapter.querySelector(".videos").style.display === "block") {
            hideAllChapters();
            return;
        }
        // hide any open section
        hideAllChaptersExcept(chapter);
    }

    function playVideo(videoButton) {
        const videoWrap = videoButton.parentElement.parentElement.parentElement;
        console.log("wrap", videoWrap);
        const chapter = videoWrap.parentElement;
        [].slice.call(document.querySelectorAll(".video-player")).forEach(section => section.style.display = "none");
        const videoPlayer = videoWrap.querySelector(".video-player");
        const video = videoPlayer.querySelector("video");
        videoPlayer.style.display = "block";
        video.play();
        videoButton.textContent = videoButton.getAttribute("data-" + videoButton.textContent.toLowerCase());

        if (videoButton.textContent === "Play") {
            video.pause()
        }
    }

    document.addEventListener("click", function(event) {
        if (eventTargetHasClass(event, "show-chapter")) {
            event.preventDefault();
            showChapter(event.target);
            return;
        }

        if (eventTargetHasClass(event, "video-toggle")) {
            event.preventDefault();
            playVideo(event.target);
            return;
        }
    });
})();
JS;
    }

}

/**
 * Class Chapter
 */
class Chapter extends Template
{
    /** @var string $template */
    public $template = <<<HTML
<div class="chapter">
    <h2><a href="#" class="show-chapter" data-name="{{name}}">{{name}}</a></h2>
    <div class="videos">{{videos}}</div>
</div>
HTML;

    /** @var string $name */
    public $name;

    /**
     * @param string $name
    */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getVideos() : string
    {
        $dir = "./{$this->name}";
        $videos = array_values(array_filter(
            scandir($dir),
            function ($item) use ($dir) {
                return !is_dir("$dir/$item")
                    && $this->isExtn($item, Video::EXTN)
                    && !in_array($item, [".", ".."]);
            }
        ));

        $videos = array_map(
            function ($video) {
                return (new Video($this, $video))->parse();
            },
            $videos
        );

        return implode("", $videos);
    }

    /**
     * @param string $filename
     * @param string $targetExtension
     * @return bool
     */
    private function isExtn(string $filename, string $targetExtension) : bool
    {
        $parts = explode(".", $filename);
        $actualExtension = $parts[count($parts) - 1];
        return strtolower($targetExtension) === strtolower($actualExtension);
    }
}

/**
 * Class Video
 */
class Video extends Template
{
    const EXTN = "mp4";

    /** @var string $template */
    public $template = <<<HTML
<div class="video">
    <div class="title">
        <h3>
            {{name}}
            <span class="video-toggle" data-play="Stop" data-stop="Play">Play</span>
        </h3>
    </div>
    <div class="video-player">
        <video src="{{videoSource}}" controls>
    </div>
</div>
HTML;

    /** @var string $name */
    public $name;

    /** @var Chapter $chapter */
    public $chapter;

    /**
     * @param Chapter $chapter
     * @param string $name
     */
    public function __construct(Chapter $chapter, string $name)
    {
        $this->chapter = $chapter;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getVideoSource() : string
    {
        return "./{$this->chapter->name}/{$this->name}";
    }
}

file_put_contents(
    "./index.html", 
    (new Page("GDQuest"))->parse()
);