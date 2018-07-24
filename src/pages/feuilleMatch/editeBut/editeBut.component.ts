import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { MatchsService } from '../../../services/matchs.service';
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
    templateUrl: './editeBut.component.html'
  }
)
export class EditeButComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public infoEquipes = [{id: 'r345', nom: 'Nom equipe'}];
  public ev: FeuilleMatch;
  public resultat1 = 0;
  public resultat2 = 0;
  public range = R.range(0, 31);
  public optionsJoueurs = [];
  public isEq;
  public erreurs = {};
  public form: FormGroup;
  public editingChrono = false;
  public chrono;

  @select(['mesMatchs', 'joueurs']) $joueurs;

  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public matchServ: MatchsService,
    public params: NavParams,
    public fb: FormBuilder,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push(
      this.$joueurs.subscribe(j => {
        this.optionsJoueurs = j.toJS();
      })
    );
    this.infoEquipes = this.matchServ.equipesMatch();
    console.log('info equipes', this.infoEquipes);

    this.ev = this.params.get('ev');
    this.ev = feuilleMatchForInput(this.ev);

    // this.isEq = _.bind((joueur) => {
    //   console.log('filter');
    //   return joueur.id_equipe === this.ev.id_equipe
    // }, this);
    this.prep();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  nomJoueur(id) {
    let data = R.find(R.propEq('id', id), this.optionsJoueurs);
    if (data) {
      return data.nom;
    } else {
      return '-'
    }
  }

  validate() {
    R.forEach(
      (ctrl: FormControl) => ctrl.updateValueAndValidity(),
      R.values(R.pick(['id_membre', 'id_membre_passe', 'id_membre_passe2', 'id_membre_gardien'], this.form.controls))
    );
    console.log(this.form);
    return this.form.valid;
  }
  async soumettre() {

    if (!this.validate()) {
      return;
    }

    let vals = R.pickBy((v, k) => R.has(k, this.ev) && v != this.ev[k], this.form.value);

    if (_.size(vals) === 0) {
      this.navCtrl.pop();
      return;
    }

    console.log('updated: ', vals);
    let succes = this.fmServ.sauvegarderEv(R.pipe(
      R.merge(this.ev),
      feuilleMatchForDb
    )(this.form.value));

    if (succes) {
      this.navCtrl.pop();
    }
    // this.fmServ.sauvegarderEv(R.merge(this.ev, this.form.value))
  }

  updateChronoCtrl(val) {
    console.log('chrono change', val);
    this.form.controls.chrono.setValue(val);
    this.chrono = val;

  }
  prep() {
    let valeurs = getEvValsForInput([
      'id_membre',
      'id_equipe',
      'chrono',
      'resultat',
      'resultat_adversaire',
      'but_special',
      'id_membre_passe',
      'id_membre_passe2',
      'id_membre_gardien'
    ])(this.ev);
    // let valeurs =
    //   R.pipe(
    //     R.pickAll([
    //       'id_membre',
    //       'id_equipe',
    //       'chrono',
    //       'resultat',
    //       'resultat_adversaire',
    //       'but_special',
    //       'id_membre_passe',
    //       'id_membre_passe2',
    //       'id_membre_gardien'
    //     ]),
    //     R.map(val => val === undefined ? null : val),
    //     feuilleMatchForInput
    //   )(this.ev);


    console.log('valeurs initiales', valeurs);
    this.chrono = this.ev.chrono;

    this.form = this.fb.group({
      id_membre: [valeurs.id_membre, [Validators.required, _.bind(this.validateJoueur(true), this)]],
      id_equipe: [valeurs.id_equipe, [Validators.required]],
      chrono: [valeurs.chrono, [Validators.required, Validators.pattern(/^[0-4]\d:\d\d$/)]],
      resultat: [valeurs.resultat, [Validators.required, Validators.min(0), Validators.max(30)]],
      resultat_adversaire: [valeurs.resultat_adversaire, [Validators.required, Validators.min(0), Validators.max(30)]],
      but_special: [valeurs.but_special, Validators.pattern(/^(|penalite)$/)],
      id_membre_passe: [valeurs.id_membre_passe, _.bind(this.validateJoueur(true), this)],
      id_membre_passe2: [valeurs.id_membre_passe2, _.bind(this.validateJoueur(true), this)],
      id_membre_gardien: [valeurs.id_membre_gardien, _.bind(this.validateJoueur(false, true), this)],
    });
  }

  validateJoueur(memeEquipe: boolean, isGardien = false){


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

}
