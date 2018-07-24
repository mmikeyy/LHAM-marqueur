import { Component, EventEmitter, OnInit, OnDestroy, Input } from '@angular/core';
import { FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { AlertController, NavController, NavParams, ToastController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { MatchsService } from '../../../services/matchs.service';
import { CodePunition } from '../../../store/matchs/matchs.types';
import {
  FeuilleMatch,
  feuilleMatchForDb,
  feuilleMatchForInput,
  getEvValsForInput
} from '../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: '',
    templateUrl: './editePunition.component.html'
  }
)
export class EditePunitionComponent implements OnInit, OnDestroy {

  public chrono = '00:00';
  public codesFrequentsOuRecents: [string, boolean][] = [];
  public codesFrequentsOuRecentsInput: string[] = [];
  public codesInconnus: [string, string][] = [];
  public codesInconnusInput: string[] = [];
  public codesPunitionNumeriques = [];
  public editingChrono = false;
  public ev;
  public form: FormGroup;
  public optionsJoueurs = [];
  public R = R;
  public subs: Subscription[] = [];
  public temps_punitions: number[] = [];

  @select(['mesMatchs', 'joueurs']) $joueurs;


  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public matchServ: MatchsService,
    public params: NavParams,
    public fb: FormBuilder,
    public navCtrl: NavController,
    public alertCtrl: AlertController,
    public toastCtrl: ToastController
  ) {

  }

  ngOnInit() {
    this.subs.push(
      this.$joueurs.subscribe(j => {
        this.optionsJoueurs = j.toJS();
      })
    );
    this.ev = this.params.get('ev');

    this.prep()
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  prep() {
    let valeurs = getEvValsForInput([
      'chrono',
      'id_equipe',
      'id_membre',
      'duree_punition',
      'codes_punition',
      'expulsion'
    ])(this.ev);


    if (!valeurs.duree_punition) {
      valeurs.duree_punition = this.store.getState().matchs.getIn(['config', 'temps_punitions', 0], 1)
    }

    console.log('valeurs initiales', valeurs);
    this.chrono = this.ev.chrono;

    this.codesPunitionNumeriques = (valeurs.codes_punition.match(/\d+/) || []) as string[];

    let frequents = R.pipe(
      R.filter(R.prop('frequent')),
      R.map((code: CodePunition) => [code.code, R.contains(code.code, this.codesPunitionNumeriques)])
    )(this.matchServ.codesPunitions);

    this.codesFrequentsOuRecents = R.concat(
      frequents as [string, boolean][],
      R.pipe(
        R.filter((code: string) => this.matchServ.codeExiste(code) && !this.matchServ.isFrequentCode(code)),
        R.map((code: string) => [code, true] )
      )(this.codesPunitionNumeriques) as [string, boolean][]
    );
    console.log('......... freq ou rec', this.codesFrequentsOuRecents);
    this.codesFrequentsOuRecentsInput = R.clone(this.codesPunitionNumeriques);

    this.codesInconnus =
      R.pipe(
        R.filter((code: string) => !this.matchServ.codeExiste(code)),
        R.map(code => [code, this.descCode(code, valeurs.codes_punition)] as [string, string])
      )
      (this.codesPunitionNumeriques);

    this.codesInconnusInput = R.map(data => data[0], this.codesInconnus);

    this.form = this.fb.group({
      id_membre: [valeurs.id_membre, [Validators.required, _.bind(this.validateJoueur(true), this)]],
      id_equipe: [valeurs.id_equipe, [Validators.required]],
      chrono: [valeurs.chrono, [Validators.required, Validators.pattern(/^[0-4]\d:\d\d$/)]],
      duree_punition: [valeurs.duree_punition, [Validators.required, Validators.min(0), Validators.max(30)]],
      codesPunitionNumeriques: [this.codesPunitionNumeriques, Validators.required],
      expulsion: [!!valeurs.expulsion],
      codesFrequentsOuRecentsInput: [this.codesFrequentsOuRecentsInput]
    });
  }

  updateFrequents(ids: string[]) {
    this.codesFrequentsOuRecents = R.concat(
      this.codesFrequentsOuRecents,
      R.pipe(
        R.without(R.map(R.nth(0), this.codesFrequentsOuRecents)),
        R.map(code => [code, true])
      )(ids) as [string, boolean][]
    );
    this.form.controls['codesFrequentsOuRecentsInput'].setValue(R.clone(ids));
  }

  updateCodesPunitionsNumeriques(ids: string[]) {
    this.form.controls['codesPunitionNumeriques'].setValue(R.clone(ids));
  }

  updateChronoCtrl(val) {
    console.log('chrono change', val);
    this.form.controls.chrono.setValue(val);
    this.chrono = val;

  }

  descCode(code, chaine): string {
    let regexp = RegExp(`(?:^|[^0-9])(\\d+[^0-9]+)`);
    let match = chaine.match(regexp);
    if (match.length === 0) {
      return '';
    }
    return R.last(match) as string;
  }

  validate() {
    return this.form.valid;
  }

  async soumettre() {
    if (!this.validate()) {
      let toast = this.toastCtrl.create({
        message: 'Des valeurs doivent être entrées ou corrigées',
        duration: 2500
      });
      toast.present();
      return;
    }
    let vals: FeuilleMatch = R.pick([
      'id_equipe',
      'id_membre',
      'chrono',
      'duree_punition',
      'expulsion'
    ], this.form.value) as FeuilleMatch;
    vals = feuilleMatchForDb(vals);
    vals.codes_punition = this.form.value.codesPunitionNumeriques
      .map(code => this.matchServ.descCodePunition(code))
      .join('; ');

    vals = R.merge(this.ev, vals);

    let succes = await this.fmServ.sauvegarderEv(vals);
    if (succes) {
      this.navCtrl.pop();
    }
  }

  validateJoueur(memeEquipe: boolean, isGardien = false) {


    return (ctrl: FormControl) => {
      // console.log('validation');
      let id = ctrl.value;
      if (!id) {
        return null;
      }
      let data = R.find(R.propEq('id', id), this.optionsJoueurs);
      if (!data) {
        // console.log('introuvable');
        return {introuvable: true};
      }
      if (isGardien && data.position !== 2) {
        return {pasGardien: true};
      }

      let ok = R.eqProps('id_equipe', data, (this.form || {value: {}}).value);
      if (!memeEquipe) {
        ok = !ok;
      }
      // console.log('data.id_equipe', data.id_equipe, 'form id_equipe', (this.form || {value: {}}).value['id_equipe'], 'ok', (ok ? 'oui' : 'non'));
      if (!ok) {
        // console.log('mauv equipe');
        return {equipe_diff: true};
      }
      return null;
    }
  }

  changed() {
    this.validate();
  }

  alertInfoCodes() {
    let alert = this.alertCtrl.create({
      title: 'Codes de punition',
      message: `      
      <p>La <b>liste complète</b> comprend tous les codes. Sous cette liste paraissent les choix les plus fréquents,
      ou les codes déjà choisis.</p>
      
      <p>Si d'autres codes devraient être inclus dans la liste, parlez-en au gestionnaire de la ligue.</p>`,
      buttons: [
        {
          text: 'OK'
        }
      ]
    }
    );
    alert.present();
  }

  toggleCode(checked, code) {
    let fn = checked ? R.append : R.without;
    this.form.controls['codesPunitionNumeriques'].setValue(fn(code, this.form.controls['codesPunitionNumeriques'].value));
  }
}
