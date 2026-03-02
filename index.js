/*
   Copyright 2018 Locomote Limited
   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at
       http://www.apache.org/licenses/LICENSE-2.0
   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/
// TODO: only inject HTML if the version is iOS

// Injects a banner in the DOM and controls when to show it depending on iOS verson

// The min iOS version where the banner is showed
var miniOSVersionSupported = '11.3';

console.log(`iOS PWA Banner: Min iOS version supported: ${miniOSVersionSupported}`);

// Use for testin. Set to true always show the banner (any platform)
let disableBannerCheck = false;

// Detects if device is in standalone mode
const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);

// Detects if device is iphone, ipad or ipod
function isIos(){
    console.log(window.navigator.userAgent)
    const userAgent = window.navigator.userAgent.toLowerCase();
    return /iphone|ipad|ipod/.test( userAgent );
}

/**
  * Check if device platform is iOS and get the iOS version
  * @return     Array with the platform version e.g ['0S 11_2',11,'2']
  */
function iOSversion(){
    console.log(window.navigator.platform)
    if (/iP(hone|od|ad)/.test(window.navigator.platform)) {
        // supports iOS 2.0 and later: <http://bit.ly/TJjs1V>
        var v = (window.navigator.appVersion).match(/OS (\d+)_(\d+)_?(\d+)?/);
        return [parseInt(v[1], 10), parseInt(v[2], 10), parseInt(v[3] || 0, 10)];
    }
}

/**
  * Check if the devices version is same or above the min version
  * @return false if version is pre minVersionSupported or not iOS. true if version is equal or post minVersionSupported
  */
function isMinIosVersionSupported(){
    if ( !isIos() ) return false;
    let platformVersionArray = iOSversion();
    if ( platformVersionArray ){
        let minVersionSupportedArray = miniOSVersionSupported.split('.').map( (v) => {return parseInt( v )} );       // [11,3]
        let [ minVersionSupported, minSubversionSupported ] = minVersionSupportedArray;
        let [ platformVersion, platformSubversion ] = platformVersionArray;
        console.log(`Device iOS version: ${platformVersion}.${platformSubversion}`);
        console.log('Min iOS verson supported: ' + minVersionSupported);
        if ( platformVersion > minVersionSupported ) {
            return true;
        }
        if ( platformVersion == minVersionSupported && platformSubversion >= minSubversionSupported){
            return true;
        }
        return false;
    } else {
        return false;
    }
}

/**
  * A DOM element than can be showed and hidded.
  * @param      selector The element selector.
  * @return     Methods to show or hide, or register a click event.
  */
let Element = function( selector ){
    function showElement(){
        document.querySelector( selector ).classList.remove( 'hide-element' );
        document.querySelector( selector ).classList.add( 'show-element' );
    }
    function hideElement(){
        document.querySelector( selector ).classList.remove( 'show-element' );
        document.querySelector( selector ).classList.add( 'hide-element' );
    }
    let addEventListener = (fn) => {
        // iOS devices only works with 'touchstart' events
        let eventType = 'click';
        if ( !isIos ) eventType = 'touchstart';
        document.addEventListener( eventType, event => {
            if (!event.target.closest( selector )) return;
            fn( event );
        }, false );
    }
    return {
        show:               showElement,
        hide:               hideElement,
        addEventListener:   addEventListener
    }
}

let BannerText = function( selectorOne, selectorTwo){
    let textOneElement = new Element( selectorOne );
    let textTwoElement = new Element( selectorTwo );
    function showTextOne(){
        textOneElement.show();
        textTwoElement.hide();
    }
    function showTextTwo(){
        textTwoElement.show();
        textOneElement.hide();
    }
    return {
        showTextOne: showTextOne,
        showTextTwo: showTextTwo
    }
}

let getBannerHtml = function( appName ){
    return `
    <div class="ios-pwa-banner hide-element">
        <div class="app-icon"><img class="vertical-align" src="img/locomote-logo.png" /></div>
        <div class="banner-text">
            <span class="banner-text-one">1.　<img src="img/share-action-icon.png" />　をクリック<br />2.「ホーム画面に追加」をクリック</span>
            <span class="banner-text-two hide-element">スマホのホーム画面に追加できます</span>
        </div>
        <div class="closing-button">
            <img class="vertical-align" src="img/cancel.png" />
        </div>
    </div>`;
}

document.addEventListener("DOMContentLoaded", function() {

    if ( disableBannerCheck || ( isIos() && isMinIosVersionSupported() && !isInStandaloneMode()) ) {

        // Get the app title from the header. More info: https://github.com/locomote-sh/build-web-manifest
        let appTitle = document.head.querySelector('[name=apple-mobile-web-app-title][content]').content || 'APP';

        // Create a container element and insert the banner HTML
        let bannerContainerElement = document.createElement('div');
        bannerContainerElement.innerHTML = getBannerHtml(appTitle);
        document.body.appendChild(bannerContainerElement);

        // Instantiate elements
        let iOSBannerElement =         new Element('.ios-pwa-banner');
        let closingButtonElement =     new Element('.closing-button');
        let bannerText =               new BannerText( '.banner-text-one', '.banner-text-two' );

        // show the banner only on iOS
        iOSBannerElement.show();

        // register event listeners
        closingButtonElement.addEventListener(( event ) => {
            iOSBannerElement.hide();
        });

        let textOneVisible = true;
        iOSBannerElement.addEventListener(( event ) => {
            if ( textOneVisible ){
                bannerText.showTextOne();
                textOneVisible = false;
            }else{
                bannerText.showTextTwo();
                textOneVisible = true;
            }
        });
    }else{
        console.log(`iOS version not supporting PWA. Not showing the banner.`)
    }
});
