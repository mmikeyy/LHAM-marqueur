import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AlertController, NavController, NavParams, ToastController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { MesMatchsActions } from '../../../store/mesMatchs/mesMatchs.actions';
import {
  FeuilleMatch,
  Fusillade,
  getEvValsForInput,
  JoueurMatch
} from '../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../store/store';
import * as R from 'ramda';
import { interchangeProps } from '../../../types/types';
import { ChoisirJoueursFusilladeComponent } from '../fusillade/choisirJoueurs/choisirJoueursFusillade.component';

@Component(
  {
    selector: '',
    templateUrl: './editeFusillade.component.html'
  }
)
export class EditeFusilladeComponent implements OnInit, OnDestroy {

  public ev: FeuilleMatch;
  public subs: Subscription[] = [];
  public liste: Fusillade[];
  public rondes: number[] = [];
  public prochaineRonde: number;
  public form: FormGroup;
  public idFMFusilladeChargee;
  public chrono = '00:00';
  public range = R.range(0, 31);
  public modeRonde = {};
  public choixRondes = {};
  public tousJoueurs: JoueurMatch[];
  public joueursEq: JoueurMatch[][] = [[], []];
  public R = R;
  public cancelChanges = false;
  public buttonsDeactivated = false;
  public timeoutDeactivate;

  public listeOrig: Fusillade[];
  public evOrig;

  public choix = [];

  @select(['mesMatchs', 'idFMFusilladeChargee']) $idFMFusilladeChargee;
  @select(['mesMatchs', 'fusillade']) $fusillade;
  @select(['mesMatchs', 'joueurs']) $joueurs;

  constructor(
    public store: NgRedux<IAppState>,
    public params: NavParams,
    public mesMatchsAct: MesMatchsActions,
    public fmServ: FmService,
    public navCtrl: NavController,
    public fb: FormBuilder,
    public toastCtrl: ToastController,
    public alertCtrl: AlertController
  ) {
    this.ev = this.params.get('ev');
    this.evOrig = R.clone(R.pick(['id_equipe', 'resultat', 'resultat_adversaire'], this.ev));
  }

  ngOnInit() {
    this.subs.push(
      this.$idFMFusilladeChargee.subscribe(id => {
        this.idFMFusilladeChargee = id;
        this.loadFusillade();
      }),
      this.$fusillade.subscribe(liste => {
        this.liste = liste.toJS();
        this.rondes = R.pipe(
          R.pluck('ronde'),
          R.uniq,
          R.sortBy(R.identity)
        )(this.liste) as number[];
        this.prochaineRonde = (R.last(this.rondes) || 0) + 1;
        if (!this.listeOrig) {
          this.listeOrig = R.clone(this.liste);
        }
      }),
      this.$joueurs.subscribe(joueurs => {
        this.tousJoueurs = joueurs.filter(j => j.position !== 2).toJS();

        let a = R.pluck('id', this.fmServ.equipes);


        this.joueursEq = R.map(
          (eq: any) => R.filter(R.propEq('id_equipe', eq.id), this.tousJoueurs)
        )(this.fmServ.equipes);
      })
    );

    this.prep();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  ionViewCanLeave() {
    if (this.cancelChanges || !this.chgEv() && !this.chgFusillade()) {
      return true;
    }
    let alert = this.alertCtrl.create({
      message: 'Annuler les changements?',
      buttons: [
        {
          text: 'NON'
        },
        {
          text: 'OUI',
          handler: () => {
            this.cancelChanges = true;
            this.navCtrl.pop();
          }
        }
      ]
    });
    alert.present();
    return false;
  }

  prep() {
    let valeurs = getEvValsForInput([
      'id_equipe',
      'chrono',
      'resultat',
      'resultat_adversaire'
    ])(this.ev);
    this.chrono = this.ev.chrono;

    this.form = this.fb.group({
      id_equipe: [valeurs.id_equipe, Validators.required],
      chrono: [valeurs.chrono, Validators.pattern(/[0-4]\d:[0-5]\d/)],
      resultat: [valeurs.resultat, [Validators.required, Validators.min(0)]],
      resultat_adversaire: [valeurs.resultat_adversaire, [Validators.required, Validators.min(0)]]
    })
  }


  loadFusillade(force = false) {
    if (force || this.ev.id !== this.store.getState().mesMatchs.get('idFMFusilladeChargee')) {
      this.mesMatchsAct.getFM(this.fmServ.idMatch, this.ev.id);
    }
  }

  ronde(noRonde) {
    return R.filter(R.propEq('ronde', noRonde), this.liste);
  }

  idsJoueursRonde(ronde, noEq) {
    return R.pipe(
      R.filter(R.propEq('ronde', ronde)),
      R.pluck('id_joueur' + noEq),
      R.filter(R.identity as () => boolean)
    )(this.liste);
  }

  reorder(ronde, noEq, ev) {
    let isRonde = R.propEq('ronde', ronde);
    let joueurProp = `id_joueur${noEq}`;
    let butProp = `but${noEq}`;
    let listeRondeEq = R.filter(R.allPass([isRonde, R.prop(joueurProp)]), this.liste);
    if (listeRondeEq.length === 1) {
      return;
    }
    let idJoueur = (index: number) => R.pipe(
      R.nth(index),
      R.prop(joueurProp)
    )(listeRondeEq) as string;

    let idJoueurFrom = idJoueur(ev.from);
    let idJoueurTo = idJoueur(ev.to);

    let indexJoueurFrom = R.findIndex(R.allPass([isRonde, R.propEq(joueurProp, idJoueurFrom)]), this.liste);
    let indexJoueurTo = R.findIndex(R.allPass([isRonde, R.propEq(joueurProp, idJoueurTo)]), this.liste);
    this.liste =interchangeProps(indexJoueurFrom, indexJoueurTo, [joueurProp, butProp], this.liste);
  }

  getBut(idJoueur, ronde, noEq) {
    let el = R.find(R.allPass([
      R.propEq(`id_joueur${noEq}`, idJoueur),
      R.propEq('ronde', ronde)
    ]), this.liste);
    if (!el) {
      return;
    }
    return el[`but${noEq}`]
  }

  updateBut(idJoueur, ronde, noEq, ctrl) {
    let propJoueur = `id_joueur${noEq}`;
    let propBut = `but${noEq}`;
    let index = R.findIndex(R.allPass([
      R.propEq(propJoueur, idJoueur),
      R.propEq('ronde', ronde)
    ]), this.liste);
    if (index < 0) {
      return;
    }

    this.liste = R.assocPath([index, propBut], ctrl.checked, this.liste);
  }

  ajoutPeriode() {
    console.log('ajout per')
  }

  edit(ronde) {
    this.navCtrl.push(ChoisirJoueursFusilladeComponent, {ronde})
  }

  cons(nb) {
    console.log('ronde', nb);
  }


  setModeRonde(ronde, mode) {
    if (mode === 'choix') {
      for (let noEq of [1, 2]) {
        this.choixRondes = R.assocPath([ronde, 'choix', noEq], this.idsJoueursRonde(ronde, noEq), this.choixRondes);
      }

    }
    this.modeRonde[ronde] = mode;
  }

  choisir(id, ronde, noEq, ctrl) {
    let lens = R.lensPath([ronde, 'choix', noEq]);
    let curVal = R.view(lens, this.choixRondes) as string[];
    let newVal;
    if (ctrl.checked) {
      newVal = R.append(id, curVal);
    } else {
      newVal = R.without(id, curVal);
    }
    this.choixRondes = R.set(lens, newVal, this.choixRondes);
  }

  noEquipe(idEquipe) {
    return this.fmServ.equipes[0].id === idEquipe ? 1 : 2;
  }

  sauvegarderChoixRonde(ronde) {
    if (this.modeRonde[ronde] === 'choix') {
      console.log('sauvegarder', ronde);
      let choix = [1, 2].map(noEq => R.uniq(R.pathOr([], [ronde, 'choix', noEq], this.choixRondes)));
      if (choix[0].length !== choix[1].length) {
        this.toast('Choisissez le même nombre de joueurs dans chaque équipe');
        return;
      }
      if (choix[0].length === 0) {
        this.toast('Au moins un joueur de chaque équipe dans chaque ronde');
        return;
      }
      console.log(choix);

      let buts1 = R.pipe(
        R.filter(
          R.allPass(
            [
              R.propEq('ronde', ronde),
              R.pipe(
                R.prop('id_joueur1'),
                R.flip(R.contains)(choix[0])
              )
            ]
          )
        ),
        R.map(R.pick(['id_joueur1', 'but1'])),
        R.uniqWith(R.prop('id_joueur1'))
      )(this.liste);

      let buts2 = R.pipe(
        R.filter(R.allPass([R.propEq('ronde', ronde), R.pipe(R.prop('id_joueur2'), R.flip(R.contains)(choix[1]))])),
        R.map(R.pick(['id_joueur2', 'but2'])),
        R.uniqWith(R.prop('id_joueur2'))
      )(this.liste);

      let nouv1 = R.map(id => ({id_joueur1: id, but1: false}), R.difference(choix[0], R.pluck('id_joueur1', buts1)));
      let nouv2 = R.map(id => ({id_joueur2: id, but2: false}), R.difference(choix[1], R.pluck('id_joueur2', buts2)));

      buts1 = R.concat(buts1, nouv1);
      buts2 = R.concat(buts2, nouv2);

      let items = [];
      let base = {
        id: null,
        id_match: this.fmServ.idMatch,
        id_fm: this.fmServ.currentEv.id,
        ronde
      };
      buts1.forEach((val, ind) => {
        items.push(R.pipe(
          R.merge(buts2[ind]),
          R.merge(base),
          R.assoc('ordre', ind)
        )(val));
      });

      console.log('items', items);

      this.mesMatchsAct.updateRonde(ronde, items);

    } else if (this.modeRonde[ronde] === 'sort') {
      this.mesMatchsAct.updateRonde(ronde, this.liste);
    }
    this.modeRonde[ronde] = '';

  }

  toast(message) {
    let toast = this.toastCtrl.create({
      message,
      duration: 3000
    });
    toast.present();
  }

  chgFusillade() {
    return !R.equals(R.dissoc('id', this.liste), R.dissoc('id', this.listeOrig))
  }
  chgEv() {
    return !this.form || !R.equals(R.pick(['id_equipe', 'resultat', 'resultat_adversaire'], this.form.value), this.evOrig)
  }

  editingRonde() {

    return R.pipe(
      R.values,
      R.filter(R.identity as () => boolean),
      R.length
    )(this.modeRonde)
  }

  trackLine(line) {
    return line.id_joueur1 + ':' + line.id_joueur2 + (line.but1 ? ':1' : ':2')+ (line.but2 ? ':1' : ':2')
  }

  async soumettre() {
    let succes = await this.fmServ.sauvegarderEvFusillade(
      this.fmServ.idMatch,
      this.fmServ.currentEv.id,
      this.chgEv() ? R.merge(this.ev, this.form.value) : null,
      this.chgFusillade() ? this.liste : null);
    if (succes) {
      this.cancelChanges = true;
      this.navCtrl.pop();
    }
  }

  ajoutRonde() {
    let nouvelleRonde = this.prochaineRonde;
    let item = {
      id: null,
      id_match: this.fmServ.idMatch,
      id_fm: this.fmServ.currentEv.id,
      ronde: nouvelleRonde,
      id_joueur1: this.joueursEq[0][0].id,
      id_joueur2: this.joueursEq[1][0].id,
      but1: false,
      but2: false
    };
    this.mesMatchsAct.updateRonde(nouvelleRonde, [item]);
    this.setModeRonde(nouvelleRonde, 'choix')

  }

  effacerRonde() {
    if (this.rondes.length === 0) {
      return;
    }
    let ronde = R.last(this.rondes);
    this.mesMatchsAct.updateRonde(ronde, []);
    this.toast(`La ronde ${ronde} a été effacée`);
    this.deactivateButtons();
  }

  deactivateButtons() {
    if (this.timeoutDeactivate) {
      clearTimeout(this.timeoutDeactivate);
    }
    this.timeoutDeactivate = setTimeout(() => {
      this.buttonsDeactivated = false;
      this.timeoutDeactivate = null;
    }, 2000);
    this.buttonsDeactivated = true;
  }
}
