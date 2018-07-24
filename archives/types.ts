import { SafeHtml, SafeResourceUrl } from '@angular/platform-browser';
import { TranslateService } from '@ngx-translate/core';
import { Map, List, fromJS } from 'immutable';
import * as _ from 'lodash';
import * as R from 'ramda';
import * as moment from 'moment';
import { TypedRecord } from 'typed-immutable-record';
// import * as Modernizr from 'Modernizr';
import { Observable } from 'rxjs/Observable';
import { FromEventObservable } from 'rxjs/observable/FromEventObservable';
import { async } from 'rxjs/scheduler/async';
import { Subject } from 'rxjs/Subject';
import { EventEmitter } from '@angular/core';
declare let $: JQueryStatic;
declare let modernizr;

export const breakpoints = {
  extraSmall: 0,
  small: 320,
  medium: 700,
  mediumLarge: 1100,
  large: 1300,
  extraLarge: 99999
};
export interface Viewport {
  isMinWidth?: (number) => boolean;
  isMaxWidth?: (number) => boolean;
  width?: number;
  height?: number;
  stepBelow?: string;
  stepAbove?: string;
  onResize?: Observable<{}>;
  getPixelRatio?: () => number;
}

export let processResize;
export let onOrientationChange = Observable.fromEvent(window, 'orientationchange');
export let onResizeOrOrientation = new EventEmitter();
export let viewport: Viewport = {};
viewport.getPixelRatio = () => (window.outerWidth - 8) / window.innerWidth;
viewport.isMinWidth = tag => modernizr.mq(`(min-width: ${breakpoints[tag] || breakpoints['content']}px)`);
viewport.isMaxWidth = tag => modernizr.mq(`(max-width: ${breakpoints[tag] || breakpoints['content']}px)`);
viewport.width = window.innerWidth;
viewport.height = window.innerHeight;
viewport.onResize = Observable.fromEvent(window, 'resize').merge(onOrientationChange)
  ;
viewport.onResize
  .throttleTime(100, async, {leading: true, trailing: true})
  .subscribe(() => {
    processResize();
    // console.log('vp original ev', R.pick(['width', 'height', 'stepBelow', 'stepAbove'], viewport));
    if (viewport.width <= breakpoints.mediumLarge) {
      $('body').addClass('narrow');
    } else {
      $('body').removeClass('narrow');
    }
  });

processResize = () => {
  viewport.width = window.innerWidth;
  viewport.height = window.innerHeight;
  for (let step of ['extraLarge', 'large', 'mediumLarge', 'medium', 'small', 'extraSmall']) {
    if (breakpoints[step] < viewport['width']) {
      viewport.stepBelow = step;
      break;
    }
  }
  for (let step of ['extraSmall', 'small', 'medium', 'mediumLarge', 'large', 'extraLarge']) {
    if (breakpoints[step] > viewport['width']) {
      viewport.stepAbove = step;
      break;
    }
  }
  onResizeOrOrientation.emit();
};
processResize();

/**
 * Created by micra on 2016-09-25.
 */
export interface CssSpec {
  '@font-face'?: string;
  '@keyframes'?: string;
  'align-content'?: string;
  'align-items'?: string;
  'align-self'?: string;
  'animation'?: string;
  'animation-delay'?: string;
  'animation-direction'?: string;
  'animation-duration'?: string;
  'animation-fill-mode'?: string;
  'animation-iteration-count'?: string;
  'animation-name'?: string;
  'animation-play-state'?: string;
  'animation-timing-function'?: string;
  'backface-visibility'?: string;
  'background'?: string;
  'background-attachment'?: string;
  'background-blend-mode'?: string;
  'background-clip'?: string;
  'background-color'?: string;
  'background-image'?: string;
  'background-origin'?: string;
  'background-position'?: string;
  'background-repeat'?: string;
  'background-size'?: string;
  'border'?: string;
  'border-bottom'?: string;
  'border-bottom-color'?: string;
  'border-bottom-left-radius'?: string;
  'border-bottom-right-radius'?: string;
  'border-bottom-style'?: string;
  'border-bottom-width'?: string;
  'border-collapse'?: string;
  'border-color'?: string;
  'border-image'?: string;
  'border-image-outset'?: string;
  'border-image-repeat'?: string;
  'border-image-slice'?: string;
  'border-image-source'?: string;
  'border-image-width'?: string;
  'border-left'?: string;
  'border-left-color'?: string;
  'border-left-style'?: string;
  'border-left-width'?: string;
  'border-radius'?: string;
  'border-right'?: string;
  'border-right-color'?: string;
  'border-right-style'?: string;
  'border-right-width'?: string;
  'border-spacing'?: string;
  'border-style'?: string;
  'border-top'?: string;
  'border-top-color'?: string;
  'border-top-left-radius'?: string;
  'border-top-right-radius'?: string;
  'border-top-style'?: string;
  'border-top-width'?: string;
  'border-width'?: string;
  'bottom'?: string;
  'box-shadow'?: string;
  'box-sizing'?: string;
  'caption-side'?: string;
  'clear'?: string;
  'clip'?: string;
  'color'?: string;
  'column-count'?: string;
  'column-fill'?: string;
  'column-gap'?: string;
  'column-rule'?: string;
  'column-rule-color'?: string;
  'column-rule-style'?: string;
  'column-rule-width'?: string;
  'column-span'?: string;
  'column-width'?: string;
  'columns'?: string;
  'content'?: string;
  'counter-increment'?: string;
  'counter-reset'?: string;
  'cursor'?: string;
  'direction'?: string;
  'display'?: string;
  'empty-cells'?: string;
  'filter'?: string;
  'flex'?: string;
  'flex-basis'?: string;
  'flex-direction'?: string;
  'flex-flow'?: string;
  'flex-grow'?: string;
  'flex-shrink'?: string;
  'flex-wrap'?: string;
  'float'?: string;
  'font'?: string;
  'font-family'?: string;
  'font-size'?: string;
  'font-size-adjust'?: string;
  'font-stretch'?: string;
  'font-style'?: string;
  'font-variant'?: string;
  'font-weight'?: string;
  'hanging-punctuation'?: string;
  'height'?: string;
  'justify-content'?: string;
  'left'?: string;
  'letter-spacing'?: string;
  'line-height'?: string;
  'list-style'?: string;
  'list-style-image'?: string;
  'list-style-position'?: string;
  'list-style-type'?: string;
  'margin'?: string;
  'margin-bottom'?: string;
  'margin-left'?: string;
  'margin-right'?: string;
  'margin-top'?: string;
  'max-height'?: string;
  'max-width'?: string;
  'min-height'?: string;
  'min-width'?: string;
  'nav-down'?: string;
  'nav-index'?: string;
  'nav-left'?: string;
  'nav-right'?: string;
  'nav-up'?: string;
  'opacity'?: string;
  'order'?: string;
  'outline'?: string;
  'outline-color'?: string;
  'outline-offset'?: string;
  'outline-style'?: string;
  'outline-width'?: string;
  'overflo'?: string;
  'overflow-x'?: string;
  'overflow-y'?: string;
  'padding'?: string;
  'padding-bottom'?: string;
  'padding-left'?: string;
  'padding-right'?: string;
  'padding-top'?: string;
  'page-break-after'?: string;
  'page-break-before'?: string;
  'page-break-inside'?: string;
  'perspective'?: string;
  'perspective-origin'?: string;
  'position'?: string;
  'quotes'?: string;
  'resize'?: string;
  'right'?: string;
  'tab-size'?: string;
  'table-layout'?: string;
  'text-align'?: string;
  'text-align-last'?: string;
  'text-decoration'?: string;
  'text-decoration-color'?: string;
  'text-decoration-line'?: string;
  'text-decoration-style'?: string;
  'text-indent'?: string;
  'text-justify'?: string;
  'text-overflow'?: string;
  'text-shadow'?: string;
  'text-transform'?: string;
  'top'?: string;
  'transform'?: string;
  'transform-origin'?: string;
  'transform-style'?: string;
  'transition'?: string;
  'transition-delay'?: string;
  'transition-duration'?: string;
  'transition-property'?: string;
  'transition-timing-function'?: string;
  'unicode-bidi'?: string;
  'vertical-align'?: string;
  'visibility'?: string;
  'white-space'?: string;
  'width'?: string;
  'word-break'?: string;
  'word-spacing'?: string;
  'word-wrap'?: string;
  'z-index'?: string;
}

export enum VersionContenu {
  publie = 1,
  perso,
  partage
}

export interface PermContenu {
  id_perm: string;
  id_editeur: string;
  proprio?: boolean;
  id_contenu: string;
  interdit?: boolean;
  perm_publier?: boolean;
  perm_publier_expire?: string|Date;
  perm_edit_expire?: string | Date;

}

export interface Saison {
  id: string;
  nom_saison: string;
  debut: string;
  fin: string;
  statut: string;
  inscription: string;
  passe: string;
}

export interface Niveau {
  id: string;
  description: string;
  abrev: string;
  ordre: number;
}

export interface ClassePermise {
  id: string;
  id_saison: string;
  id_division: string;
  id_classe: string;
  statut: string;
  efface?: number;
  nb_equipes: number;
}

export interface EquipeSaison {
  saison: string;
  id_groupe_classe: string;
  id_division: string;
  id_classe: string;
  id_nom_std: string;
  nom_std: string;
  id_equipe: string;
  ref: string;
  nb_joueurs: number;
  nb_officiels: number;
}

export interface TableauAges {
  id_division: string;
  categ: string;
  description: string;
  id: string;
  naissance_min: string;
  naissance_max: string;
  affiche: boolean;
  ordre: number;
}

export const colors = [
  'aliceblue',
 'antiquewhite',
 'aqua',
 'aquamarine',
 'azure',
 'beige',
 'bisque',
 'black',
 'blanchedalmond',
 'blue',
 'blueviolet',
 'brown',
 'burlywood',
 'cadetblue',
 'chartreuse',
 'chocolate',
 'coral',
 'cornflowerblue',
 'cornsilk',
 'crimson',
 'cyan',
 'darkblue',
 'darkcyan',
 'darkgoldenrod',
 'darkgray',
 'darkgreen',
 'darkkhaki',
 'darkmagenta',
 'darkolivegreen',
 'darkorange',
 'darkorchid',
 'darkred',
 'darksalmon',
 'darkseagreen',
 'darkslateblue',
 'darkslategray',
 'darkturquoise',
 'darkviolet',
 'deeppink',
 'deepskyblue',
 'dimgray',
 'dodgerblue',
 'firebrick',
 'floralwhite',
 'forestgreen',
 'fuchsia',
 'gainsboro',
 'ghostwhite',
 'gold',
 'goldenrod',
 'gray',
 'green',
 'greenyellow',
 'honeydew',
 'hotpink',
 'indianred',
 'indigo',
 'ivory',
 'khaki',
 'lavender',
 'lavenderblush',
 'lawngreen',
 'lemonchiffon',
 'lightblue',
 'lightcoral',
 'lightcyan',
 'lightgoldenrodyellow',
 'lightgreen',
 'lightgrey',
 'lightpink',
 'lightsalmon',
 'lightseagreen',
 'lightskyblue',
 'lightslategray',
 'lightsteelblue',
 'lightyellow',
 'lime',
 'limegreen',
 'linen',
 'magenta',
 'maroon',
 'mediumaquamarine',
 'mediumblue',
 'mediumorchid',
 'mediumpurple',
 'mediumseagreen',
 'mediumslateblue',
 'mediumspringgreen',
 'mediumturquoise',
 'mediumvioletred',
 'midnightblue',
 'mintcream',
 'mistyrose',
 'moccasin',
 'navajowhite',
 'navy',
 'oldlace',
 'olive',
 'olivedrab',
 'orange',
 'orangered',
 'orchid',
 'palegoldenrod',
 'palegreen',
 'paleturquoise',
 'palevioletred',
 'papayawhip',
 'peachpuff',
 'peru',
 'pink',
 'plum',
 'powderblue',
 'purple',
 'red',
 'rosybrown',
 'royalblue',
 'saddlebrown',
 'salmon',
 'sandybrown',
 'seagreen',
 'seashell',
 'sienna',
 'silver',
 'skyblue',
 'slateblue',
 'slategray',
 'snow',
 'springgreen',
 'steelblue',
 'tan',
 'teal',
 'thistle',
 'tomato',
 'turquoise',
 'violet',
 'wheat',
 'white',
 'whitesmoke',
 'yellow',
 'yellowgreen',
 'transparent',
 'grey'];

export const validRgbCol = (col): any => {
  if (!/^rgb\((\d{1,3},){2}\d{1,3}\)$/i.test(col)) {
    return false;
  }

  return col.match(/\d+/g).filter(v => parseInt(v, 10) > 255).length === 0;

};
export const validNamedCol = (col) => {
  return colors.indexOf(col || '') > -1;
};
export const validHexaCol = (col) => /^#([A-F0-9]{3}){1,2}$/i.test(col || '');

export const dialWidths = {
  color: 326

};

export interface Annonceur {
  id_annonceur: number;
  nom_entreprise: string;
  programmes: Programme[];
}

export interface Programme {
  type: number;
  widget: string;
}

export interface Type {
  type: string;
  desc_en: string;
  desc_fr: string;
}

export interface Format {
  id_format: string;
  h: number;
  w: number;
}

export interface Contenu {
  id_contenu: string;
  w: number;
  h: number;
  contenu: string;
  contenu2: string;
  fname: string;
  fname2: string;
  groupes_assignes: number[];
  groupes_dispo: number[];
  link: string;
  link2: string;
  debut: string;
  fin: string;
}

export interface Groupe {
  id_groupe: number;
  description: string;
  permis: boolean;
}

export interface DossierAnnonceur {
  id_annonceur: string;
  nom_entreprise: string;
  nom_resp: string;
  prenom_resp: string;
  titre_resp: string;
  genre_resp: string;
  adr1: string;
  adr2: string;
  ville: string;
  prov: string;
  code_postal: string;
  courriel: string;
  tel: string;
  tel2: string;
  fax: string;
  langue: string;
  publicistes: string[];
}

export interface ChoixMembre {
  id_membre: string;
  nom: string;
}

export interface StdEvent {
  id: string;
  description: string;
  ordre: number;
}

export const dte = (moment_value) => new Date(moment_value.format('Y-M-D H:m'));

export const cDuree = (minutes) => {
  let res = '';
  if (!minutes) {
    return '0';
  }
  if (minutes >= 60) {
    res = Math.floor(minutes / 60) + 'h';
  }
  let reste = minutes % 60;
  if (reste) {
    res += reste + (res ? '' : 'm');
  }
  return res;
};
export const durationOptions = [
  {value: '30', label: '30 min.'},
  {value: '45', label: '45 min.'},
  {value: '60', label: '60 min. (1h)'},
  {value: '90', label: '90 min (1.5h)'},
  {value: '120', label: '120 min. (2h)'},
  {value: '180', label: '180 min. (3h)'},
  {value: '240', label: '240 min. (4h)'},
  {value: '300', label: '300 min. (5h)'},
  {value: '360', label: '360 min. (6h)'},
  {value: '420', label: '420 min. (7h)'},
  {value: '480', label: '480 min. (8h)'},
  {value: '540', label: '540 min. (9h)'},
  {value: '600', label: '600 min. (10h)'},
  {value: '660', label: '660 min. (11h)'},
  {value: '720', label: '720 min. (12h)'}

];

export const calendar_fr = {
  firstDayOfWeek: 1,
  dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
  dayNamesShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
  dayNamesMin: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
  monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
  monthNamesShort: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
  today: 'Auj.',
  clear: 'Effacer'
};

export const momentDateTime = 'YYYY-MM-DD HH:mm';

export interface Video {
  type: string;
  titre: string;
  info?: string;
  url: string;
  defaut?: boolean;
  html?: string;
  safeHtml?: SafeHtml;
  pending?: boolean;
  width?: number;
  height?: number;
  safeSrc?: SafeResourceUrl;
  videoFileType?: string;
  condLoggedIn?: boolean;
  condAdmin?: boolean;
  condEditeur?: boolean;
  rang?: number;
}
export const validateLen = (obj, flds: string | string[], min: number, max: number, errObj = {}, trServ: TranslateService) => {
  let fldArray: string[];
  let result = true;
  if (_.isString(flds)) {
    fldArray = [flds as string];
  } else {
    fldArray =  flds as string[];
  }
  let msg;
  fldArray.forEach(
    fld => {
      obj[fld] = obj[fld].trim();
      let val = obj[fld];
      let len = val.length;
      if (len < min || len > max) {
        errObj[fld] = (msg || (msg = trServ.instant('msg_len_interval', {min, max})));
        result = false;
      }
    }
  );
  return result;
};

export interface OptionRecord extends TypedRecord<OptionRecord>, Option {}
export interface Option {
  label: string;
  value: string;
}

export interface OptionAny {
  label: string;
  value: any;
}

export const prepOptions = (liste, labelFld, valueFld, nullDesc?) => {
  let result = nullDesc ? [{label: nullDesc, value: null, rang: -1}] : [];
  if (Map.isMap(liste) || List.isList(liste)) {
    liste.forEach(val => {
      result.push({label: val.get(labelFld), value: val.get(valueFld), rang: val.get('rang', 0)});
    });
  } else {
    liste.forEach(val => {
      result.push({label: val[labelFld], value: val[valueFld], rang: val['rang'] || 0});
    });
  }

  result = _.sortBy(result, ['rang']);
  return  result.map(opt => _.pick(opt, ['label', 'value'])) as Option[];
};

export const prep = (mode) => {
  let std_modes = ['admin', 'pub'];
  if (std_modes.indexOf(mode) > -1) {
    $('body').addClass(mode);
  } else if (/^end_/.test(mode)) {
    mode = mode.substr(4);
    if (std_modes.indexOf(mode) > -1) {
      $('body').removeClass(mode);
    }
  } else {
    console.error('unknown prep', mode);
  }

};

export const toMap = (liste, key = 'id')  => fromJS(_.keyBy((_.isArray(liste) ? liste : [liste]), (val: any) => val[key]));

export const regexEmail = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i ;

export const validate_mdp = (control): { [key: string]: any } => {
  let val = _.trim(control.value);
  if (val !== control.value) {
    control.setValue(val);
    return;
  }

  let result: { [key: string]: any } = {};
  if (!/[A-Z]/.test(val)) {
    result['doit_inclure_maj'] = true;
  }
  if (!/[a-z]/.test(val)) {
    result['doit_inclure_min'] = true;
  }
  if (!/.*[0-9].*/.test(val)) {
    result['doit_inclure_chiffre'] = true;
  }
  if (!/[^0-9a-z]/i.test(val)) {
    result['doit_inclure_autre'] = true;
  }
  if (val.length < 6) {
    result['trop_court'] = true;
  }
  return result;
};

export const stringToClasses = (classString: string) => {
  if (!classString) {
    return [];
  }
  return R.pipe(
    R.replace(/ +/g, ' '),
    R.trim,
    R.split(' '),
    R.uniq
  )
  (classString);
};
export const undefinedToNull: (obj: {[key: string]: any}) => {[key: string]: any} = obj => {
  let res = {};
  for (let key in obj) {
    if (!obj.hasOwnProperty(key)) {
      continue;
    }
    res[key] = ((obj[key] === undefined ? null : obj[key]));
  }
  return res;
};

export const memoizedFdate = R.memoize((string, format) => {
  if (!string) {
    return null;
  }
  let date = moment(string);
  if (!date.isValid()) {
    return null;
  }
  return date.format(format);
});

export const equalArrays = (a, b) => R.equals(R.sortBy(R.identity, a), R.sortBy(R.identity, b));

export const validCourriel = val => /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i.test(val);
export const validTel = val => /^\(\d\d\d\) ?\d{3}-\d{4}( #\d+)?$/.test(val);

/**
 *
 * @param data [{a, b...}, {a, ]
 * @returns {[p: string]: any[]}
 *
 */
export const collectObjectArray = data => {
  if (!data || data.length === 0) {
    return {};
  }
  let fields = R.keys(data[0]);
  let values = R.map(R.pipe(R.pick(fields), R.values), data) as any[][];
  return R.fromPairs(R.zip(fields, R.transpose(values)));
};


