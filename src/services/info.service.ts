import { Injectable, Renderer2 } from '@angular/core';
import { LanguageService } from '../i18n/language.service';
import { XhrService } from './xhr.service';
import { GrowlService } from './growl.service';

let $: JQueryStatic = window['$'];
let aa = require('jquery-ui');

import * as _ from 'lodash';

@Injectable()
export class InfoService {

  static remote: { [key: string]: string } = {};
  static tagsDisplayed: string[] = [];

  context;
  defaultStyles = {
    'background-color': 'white',
    color: 'black',
    width: 300,
    'min-height': 200,
    'max-height': 600,
    'border-radius': 5,
    'z-index': 999999,
    position: 'fixed',
    padding: '10',
    overflow: 'auto',

    'box-shadow': '0 0 8px #222',
    border: 'solid 1px #888',
    display: 'none'
  };

  constructor(public xhr: XhrService,
              public growl: GrowlService,
              public renderer: Renderer2) {
    this.context = require.context('../assets/i18n/', true, /.*\.(txt|html)/);
  }

  infoPath(tag) {

    return `./${LanguageService.currentLang}/txt/${tag}`;

  }

  getTxt(tag) {
    let text;

    let path = this.infoPath(tag);
    if (this.context.keys().indexOf(path) > -1) {
      text = this.context(this.infoPath(tag));
      return Promise.resolve(text);
    } else if (InfoService.remote[tag]) {
      return Promise.resolve(InfoService.remote[tag]);
    } else {
      return this.xhr.get('info', 'get_info', {
        tag
      }, {overlay: true})
        .then(
          data => {
            InfoService.remote[tag] = data.info;
            return data.info;
          }
        )
        .catch((data) => {
          this.growl.xhr_error(data);
          return false;
        });
    }
  }

  display(tag, params = {}) {
    if (InfoService.tagsDisplayed.indexOf(tag) > -1) {
      return;
    }
    let styles: JQueryCssProperties = Object.assign({}, this.defaultStyles, params['styles'] || {});
    this.getTxt(tag)
      .then(
        result => {
          let text = result || '?';
          text = text
            .replace(/((\r)?\n){2,}/g, '\n\n')
            .split(/\n\n/)
            .map(par => {
              if (/^<.*>$/.test(par)) {
                return par;
              } else {
                return `<p>${par}</p>`;
              }
            })
            .join('');
          let div = $('<div>')
            .appendTo('body')
            .draggable()
            .resizable()
            .html(text)
            .css(styles);
          if (params['obj']) {
            div.position({my: 'left top', at: 'right top', of: params['obj']});
          } else if (!styles['top'] || !styles['left']) {
            this.center(div, styles['top'], styles['left']);
          }
          div
            .fadeIn(500)
            .on('click', (ev) => {
              let this_ = $(ev.currentTarget);
              this_.hide(500, () => this_.remove());
              InfoService.tagsDisplayed = _.without(InfoService.tagsDisplayed, tag);
            });
          InfoService.tagsDisplayed.push(tag);
        }
      );
  }

  center($obj, top, left) {
    left = left || ($(window).width() - $obj.width()) / 2;
    top = top || ($(window).height() - $obj.height()) / 2;
    $obj.css({
      left,
      top
    });
  }

}
