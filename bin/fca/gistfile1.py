import json

class lattice_node:
    '''used int fca_lattice'''
    def __init__(self,i,u,d,a,o,oi):
        self.intent = a
        self.object = o
        self.object_index = oi
        self.up = u
        self.down = d
        self.index = i
        self.weight = 1
    def __str__(self):
        return str([self.index,self.weight,self.intent,self.up,self.down])
    def __repr__(self):
        return repr([self.index,self.weight,self.intent,self.up,self.down])
class fca_lattice:
    '''lattice is an unsorted list of lattice_node entries
    
    >>> fca_lattice([{1,2},{2},{1,3}],lambda x:x)
    [[0, 1, {1, 2, 3}, {2, 5}, set()], [1, 4, set(), set(), {3, 4}], [2, 2, {1, 2}, {3, 4}, {0}], [3, 2, {2}, {1}, {2}], [4, 3, {1}, {1}, {2, 5}], [5, 2, {1, 3}, {4}, {0}]]
    
    '''
    def __init__(self,o,a):
        self.objects = o
        self.ASets=[set(oo) for oo in a]
        self.Asequence=[elem for elem in reduce(lambda x,y:x|y,self.ASets)]
        #initial nodes are bottom and top
        self.nodes = [lattice_node(0,set([1]),set(),set(self.Asequence),None,-1),lattice_node(1,set(),set([0]),set(),None,-1)]
        self.itop = 1 #if itop is not added here, there won't be any top
        self.ibottom = 0
        sai = self._sorted_aset_index(self.Asequence)
        for i in sai:
            self.AddIntent(self.ASets[i],i,self.ibottom)
        self.path = []
        #calc weights
        def inc_weight(n):
            n.weight+=1
        self.traverse_up(lambda p:inc_weight(p[-1]))
    def __str__(self):
        return str(self.nodes)
    def __repr__(self):
        return repr(self.nodes)
    def __getitem__(self,key):
        return self.nodes[key]
    def sort_by_weight(self,indices):
        bw = list(indices)
        bw.sort(key=lambda x:self.nodes[x].weight)
        bw.reverse()
        return bw
    def traverse_down(self,visit,node=None):
        if node == None:
            node = self.nodes[self.itop]
        for t in self.sort_by_weight(node.down):
            if t==0:
                continue
            next = self.nodes[t]
            self.path.append(next)
            visit(self.path)
            self.traverse_down(visit,next)
            del self.path[-1]
    def traverse_up(self,visit,node=None):
        if node == None:
            node = self.nodes[self.ibottom]
        for t in node.up:
            if t==0:
                continue
            next = self.nodes[t]
            self.path.append(next)
            visit(self.path)
            self.traverse_up(visit,next)
            del self.path[-1]
    def _sorted_aset_index(self,Asequence):
        a_i = {}
        for a in Asequence:
            a_i[a] = [i for i in range(len(self.ASets)) if a in self.ASets[i]]
        Asequence.sort(key=lambda x:len(a_i[x]))
        Asequence.reverse()
        done = set()
        index = []
        for a in Asequence:
            new = set(a_i[a]) - done;
            done |= new
            index += list(new)
        return index
    def _get_maximal_concept(self,intent, gen_index):
        parentIsMaximal = True
        while parentIsMaximal:
          parentIsMaximal = False
          Parents = self.nodes[gen_index].up
          for Parent in Parents:
            if intent <= self.nodes[Parent].intent:
              gen_index = Parent
              parentIsMaximal = True
              break
        return gen_index
    def AddIntent(self,intent,oi,gen_index):
        gen_index = self._get_maximal_concept(intent, gen_index)
        if self.nodes[gen_index].intent == intent:
            if oi > -1:
                self.nodes[gen_index].object = self.objects[oi]
                self.nodes[gen_index].object_index = oi
            return gen_index
        GeneratorParents = self.nodes[gen_index].up
        NewParents = []
        for Parent in GeneratorParents:#Ic&Ii != 0 | Ic&Ii == 0
            if not self.nodes[Parent].intent < intent:
                nextIntent = self.nodes[Parent].intent & intent
                Parent = self.AddIntent(nextIntent, -1, Parent)#if Ic&Ii=0, then top is returned. This could go easier
            addParent = True
            for i in range(len(NewParents)):
                if NewParents[i]==-1:
                    continue
                if self.nodes[Parent].intent <= self.nodes[NewParents[i]].intent:
                    addParent = False
                    break;
                else:
                    if self.nodes[NewParents[i]].intent <= self.nodes[Parent].intent:
                       NewParents[i] = -1
            if addParent:
              NewParents += [Parent]
        #NewConcept = (gen_index.intent, intent ), but here only intent set
        NewConcept = len(self.nodes)
        oo = None
        if oi > -1:
            oo = self.objects[oi]
        self.nodes += [lattice_node(NewConcept,set(),set(),intent,oo,oi)]
        for Parent in NewParents:
            if Parent == -1:
                continue
            #RemoveLink(Parent, gen_index, self.nodes )
            self.nodes[Parent].down -= set([gen_index])
            self.nodes[gen_index].up -= set([Parent])
            #SetLink(Parent, NewConcept, self.nodes )
            self.nodes[Parent].down |= set([NewConcept])
            self.nodes[NewConcept].up |= set([Parent])
        #SetLink(NewConcept, gen_index, self.nodes )
        self.nodes[NewConcept].down |= set([gen_index])
        self.nodes[gen_index].up |= set([NewConcept])
        return NewConcept		

from svgfig import SVG
from Tkinter import *

class lattice_diagram:
    ''' format and draw a lattice
    .. {lattice_diagram,inkscape,tkinter}
    >>> src=[ [1,2], [1,3], [1,4] ]
    >>> lattice = fca_lattice(src,lambda x:set(x))
    >>> ld = lattice_diagram(lattice,400,400)
    >>> #display using tkinter
    >>> ld.tkinter()
    >>> mainloop()
    >>> #display using inkscape
    >>> ld.svg().inkscape()
    '''
    def __init__(self,lattice,page_w,page_h):
        w = page_w
        h = page_h
        self.lattice = lattice
        self.border = (h+w)//20
        self.w = w - 2*self.border
        self.h = h - 2*self.border
        self.top = self.border
        self.dw = w
        self.dh = h
        self.topnode = self.lattice[self.lattice.itop]
        self.nlevels = 0
        self.make()
    def setPos(self,node,x,y,w,h):
        node.x = x
        node.y = y
        node.w = w
        node.h = h
    def make(self) :
        for n in self.lattice:
            n.level = -1
        self.topnode.level=0
        self.find_levels(self.topnode, self.top, 0)
        self.fill_levels()
        h = self.top-3*self.dh
        for level in self.levels:
            h += 3*self.dh
            for n in level:
                self.setPos(n,0,h,self.dw,self.dh)
        self.horizontal_align(self.xcenter)
    def find_levels(self,node,ystart,y):
        h = 3*self.dh + ystart
        y += 1
        if(len(node.down) == 0): self.nlevels = y
        for i in node.down:
            child = self.lattice[i]
            if(child.level < y) :
                self.setPos(child,0,h,self.dw,self.dh)
                child.level=y
                self.find_levels(child, h, y)
    def fill_levels(self):
        self.levels = []
        self.dh = self.h / (3*self.nlevels)
        self.nmaxlevel = 0
        for i in range(self.nlevels):
            level = [n for n in self.lattice if n.level==i]
            if len(level)>self.nmaxlevel:
                self.nmaxlevel = len(level)
            self.levels.append(level)
        self.dw = self.w / (2*self.nmaxlevel-1)
        self.xcenter = self.w+self.border
    def find_levels_new(self,min_dist):
        '''find levels via multiple sequence alignment
        .. {find_levels_new,todo}
        Algorithm (started):
            - find all paths (which are ordered sets)
            - build lattice out of paths (loosing order) (intent = node indices, extent = paths)
            - walk lattice from top to bottom breadth-first
            - adjust distances between and to already fixed nodes
            - stretch everything to meet minimal node distance requirement and convert to integer
        '''
        pass
    def horizontal_align(self,center) :
        pX = 0
        for level in self.levels:
            llen = len(level)
            if (llen%2)==0:
                pX = center - llen*self.dw + self.dw/2
            else:
                pX = center - llen*self.dw - self.dw/2
            for n in level:
                self.setPos(n,pX,n.y,self.dw,self.dh)
                pX += 2*self.dw
            self.minCrossing(level, False)
        for level in self.levels:
            self.minCrossing(level, True)
    def minCrossing(self,level, forChildren) :
        test = False
        nbTotal = 0
        nbCrossing1 = 0
        nbCrossing2 = 0
        i = 0
        j = 0
        while(i<len(level)) :
            if(test) :i = 0
            test = False
            node1 = level[i]
            j = i+1
            while(j<len(level)):
                node2 = level[j]
                nbCrossing1 = self.nbCrossing(node1.up, node2.up)
                nbCrossing2 = self.nbCrossing(node2.up, node1.up)
                if(forChildren) :
                    nbCrossing1 += self.nbCrossing(node1.down, node2.down)
                    nbCrossing2 += self.nbCrossing(node2.down, node1.down)
                if(nbCrossing1 > nbCrossing2) :
                    self.swap(level, i, j)
                    nbTotal += nbCrossing2
                    test = True
                else: nbTotal += nbCrossing1
                j += 1
            i += 1
        return nbTotal
    def swap(self,v, i, j) :
        node1 = v[i]
        node2 = v[j]
        v[i]=node2
        x = node2.x
        node2.x=node1.x
        v[j]=node1
        node1.x=x
    def nbCrossing(self,v1,v2) :
        nbCrossing = 0
        for in1 in v1:
            n1 = self.lattice[in1]
            for in2 in v2:
                n2 = self.lattice[in2]
                if(n1.x>n2.x):
                    nbCrossing += 1
        return nbCrossing
    def svg(self):
        svg = SVG("g",stroke_width="0.1pt")
        for an in self.lattice:
            gn=[self.lattice[i] for i in an.down]
            for ag in gn:
                svg.append(SVG("line",x1=an.x, y1=an.y+an.h/2, x2=ag.x, y2=ag.y+an.h/2))
        for an in self.lattice:
            txt = ','.join([str(l) for l in an.intent if l])
            node = SVG("g",font_size=an.h/2,text_anchor="middle",stroke_width="0.1pt")
            node.append(SVG("rect", x=an.x-an.w/2, y=an.y, width=an.w, height=an.h, fill="yellow"))
            node.append(SVG("text",txt, x=an.x, y=an.y+3*an.h/4, fill="black"))
            svg.append(node)
        return svg
    def tkinter(self,sx=0.5,sy=0.5):
        class ZoomLatCanv(Frame):
            def __init__(slf):
                Frame.__init__(slf,master=None)
                Pack.config(slf,fill=BOTH,expand=YES)
                slf.makeCanvas()
                slf.drawit()
                slf.master.title("Lattice")
                slf.master.iconname("Lattice")
                slf.scale = 1.0
            def Btn1Up(slf,event):
                if slf.scale < 1.0:
                    slf.scale = 1.1 / slf.scale
                else:
                    slf.scale = slf.scale * 1.1
                slf.canvas.scale('scale', event.x, event.y, slf.scale, slf.scale)
            def Btn3Up(slf,event):
                if slf.scale > 1.0:
                    slf.scale = 1.1 / slf.scale
                else:
                    slf.scale = slf.scale / 1.1
                slf.canvas.scale('scale', event.x, event.y, slf.scale, slf.scale)
            def makeCanvas(slf):
                scrW = slf.winfo_screenwidth()
                scrH = slf.winfo_screenheight()
                slf.canvas = Canvas(slf,height=scrH,width=scrW,bg='white',cursor="crosshair",
                    scrollregion=('-50c','-50c',"50c","50c"))
                slf.hscroll = Scrollbar(slf,orient=HORIZONTAL, command=slf.canvas.xview)
                slf.vscroll = Scrollbar(slf,orient=VERTICAL, command=slf.canvas.yview)
                slf.canvas.configure(xscrollcommand=slf.hscroll.set, yscrollcommand=slf.vscroll.set)
                slf.hscroll.pack(side=BOTTOM,anchor=S,fill=X,expand=YES)
                slf.vscroll.pack(side=RIGHT,anchor=E,fill=Y,expand=YES)
                slf.canvas.pack(anchor=NW,fill=BOTH,expand=YES)
                Widget.bind(slf.canvas,"<Button1-ButtonRelease>",slf.Btn1Up)
                Widget.bind(slf.canvas,"<Button3-ButtonRelease>",slf.Btn3Up)
            def drawit(slf):
                for an in self.lattice:
                    gn=[self.lattice[i] for i in an.down]
                    for ag in gn:
                        slf.canvas.create_line(an.x, an.y+an.h/2, ag.x, ag.y+an.h/2,tags='scale')
                for an in self.lattice:
                    slf.canvas.create_rectangle(an.x-an.w/2, an.y, an.x+an.w/2, an.y+an.h, fill="yellow",tags='scale')
                    slf.canvas.create_text(an.x,an.y+3*an.h/4,fill="black",text=','.join([str(l) for l in an.intent if l]),tags='scale')
        return ZoomLatCanv()
